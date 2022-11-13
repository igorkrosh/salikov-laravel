<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

use DateTime;
use DateTimeZone;

use App\Models\User;
use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\BlockAccess;
use App\Models\CourseAccess;
use App\Models\WebinarAccess;
use App\Models\Webinar;
use App\Models\Order;
use App\Models\ReferralLink;
use App\Models\JuricticRequest;
use App\Models\Setting;

use App\Events\PaymentNotification;

class TinkoffController extends Controller
{
    public function BuyCourse(Request $request, $courseId)
    {
        $request->validate([
            'price' => ['required'],
            'access' => ['required'],
            'access_days' => ['required'],
            'packet_name' => ['required']
        ]);

        $orderPrice = $request->price;

        if ($request->use_points)
        {
            $newPrice = $this->UsePoints($request->price);

            if ($newPrice <= 0)
            {
                $this->TakeFreeCourse($request, $courseId);

                return;
            }
            else 
            {
                $orderPrice = $newPrice;
            }
        }

        $courseName = Course::where('id', $courseId)->first()->name;
        $packetName = $request->packet_name;
        $userId = Auth::user()->id;

        $amount = $orderPrice * 100;
        $desc = "Покупка доступа к курсу \"$courseName\" ($packetName)";
        $orderId = time();

        $order = Order::where([['user_id', $userId], ['type', 'course'], ['object_id', $courseId]])->whereIn('status', ['INIT', 'VISIT'])->first();

        if (empty($order))
        {
            $order = new Order();

            $order->user_id = $userId;
            $order->order_id = $orderId;
            $order->type = 'course';
            $order->object_id = $courseId;
        }

        $order->days = $request->access_days;
        $order->access = $request->access;
        $order->status = 'INIT';
        $order->price = $orderPrice;

        $order->packet = $packetName;
        $order->promocode = empty($request->promocode) ? '' : $request->promocode;
        $order->points = empty($request->use_points) ? 0 : Auth::user()->active_points;

        if (!empty($request->cookie('ref_id')))
        {
            $redId = $request->cookie('ref_id');
            $referralLink = ReferralLink::where('ref_id', $redId)->first();
    
            if (!empty($referralLink))
            {
                $referralLink->requests = $referralLink->requests + 1;
            }

            $order->ref_id = $request->cookie('ref_id');
        }

        $order->save();
        
        $orderData = [
            'TerminalKey' => config('tinkoff.terminal_key'),
            'Amount' => $amount,
            'Description' => $desc,
            'OrderId' => $order->order_id,
            'NotificationURL' => url('/')."/api/buy/order/notification",
        ];

        $response = Http::withOptions([
            'verify' => false,
        ])->post('https://securepay.tinkoff.ru/v2/Init', $orderData);

        $data = $response->object();
        //$data->hash = $this->MakeHash($orderData);

        return $data;
    }

    public function TakeFreeCourse(Request $request, $courseId)
    {
        $request->validate([
            'price' => ['required'],
            'access' => ['required'],
            'access_days' => ['required'],
            'packet_name' => ['required']
        ]);

        $courseName = Course::where('id', $courseId)->first()->name;
        $packetName = $request->packet_name;
        $userId = Auth::user()->id;

        $order = new Order();

        $order->user_id = $userId;
        $order->order_id = time();
        $order->days = $request->access_days;
        $order->access = $request->access;
        $order->status = 'CONFIRMED';
        $order->price = $request->price;
        $order->object_id = $courseId;
        $order->type = 'course';
        $order->packet = $packetName;

        if (!empty($request->cookie('ref_id')))
        {
            $referralLink = ReferralLink::where('ref_id', $request->cookie('ref_id'))->first();

            if (!empty($referralLink))
            {
                $referralLink->requests = $referralLink->requests + 1;
            }

            $order->ref_id = $request->cookie('ref_id');
        }

        $order->save();

        $this->AddAccessCourse($order->object_id, $order->user_id, $order->access, $order->days);

        broadcast(new PaymentNotification($order->user_id, $order->status));
    }

    public function BuyWebinar(Request $request, $webinarId)
    {
        $request->validate([
            'price' => ['required'],
            'access_days' => ['required'],
            'packet_name' => ['required']
        ]);

        $webinarName = Webinar::where('id', $webinarId)->first()->name;
        $userId = Auth::user()->id;

        $amount = $request->price * 100;
        $desc = "Покупка доступа к вебинару \"$webinarName\"";
        $orderId = time();

        $access = WebinarAccess::where([['user_id', $userId], ['webinar_id', $webinarId]])->first();

        if (!empty($access))
        {
            return response()->json([
                'message' => 'У вас уже есть доступ к этому вебинару'
            ] , 422);
        }

        $order = Order::where([['user_id', $userId], ['type', 'webinar'], ['object_id', $webinarId]])->whereIn('status', ['INIT', 'VISIT'])->first();

        if (empty($order))
        {
            $order = new Order();

            $order->user_id = $userId;
            $order->order_id = $orderId;
            $order->type = 'webinar';
            $order->object_id = $webinarId;
        }

        
        $order->days = $request->access_days;
        $order->access = 0;
        $order->status = 'INIT';
        $order->price = $request->price;
        $order->packet = $request->packet_name;

        if (!empty($request->cookie('ref_id')))
        {
            $referralLink = ReferralLink::where('ref_id', $request->cookie('ref_id'))->first();

            if (!empty($referralLink))
            {
                $referralLink->requests = $referralLink->requests + 1;
            }

            $order->ref_id = $request->cookie('ref_id');
        }

        $order->save();

        $orderData = [
            'TerminalKey' => config('tinkoff.terminal_key'),
            'Amount' => $amount,
            'Description' => $desc,
            'OrderId' => $order->order_id,
            'NotificationURL' => url('/')."/api/buy/order/notification",
        ];

        $response = Http::withOptions([
            'verify' => false,
        ])->post('https://securepay.tinkoff.ru/v2/Init', $orderData);

        $data = $response->object();

        return $data;
    }

    public function TakeFreeWebinar(Request $request, $webinarId)
    {
        $userId = Auth::user()->id;

        $order = new Order();

        $order->user_id = $userId;
        $order->order_id = time();
        $order->type = 'webinar';
        $order->days = $request->access_days;
        $order->access = 0;
        $order->status = 'CONFIRMED';
        $order->price = 0;
        $order->object_id = $webinarId;
        $order->packet = '';

        $order->save();

        $redId = $request->cookie('ref_id');
        $referralLink = ReferralLink::where('ref_id', $redId)->first();

        if (!empty($referralLink))
        {
            $referralLink->requests = $referralLink->requests + 1;
        }

        $this->AddAccessWebinar($order->object_id, $order->user_id, $request->access_days);

        broadcast(new PaymentNotification($order->user_id, $order->status));
    }

    public function PaymentNotification(Request $request)
    {
        $orderId = $request->all()['OrderId'];
        $status = $request->all()['Status'];

        $order = Order::where('order_id', $orderId)->first();
        $order->status = $status;

        $order->save();

        if ($status != "CONFIRMED")
        {
            return;
        }
        
        if ($order->type == 'course')
        {
            $this->AddAccessCourse($order->object_id, $order->user_id, $order->access, $order->days);
            $this->SetCourseStatistic($order->user_id, $order->object_id, 'date_payment', Carbon::now()->translatedFormat('d.m.Y H:i'));
        }

        
        if ($order->type == 'webinar')
        {
            $this->AddAccessWebinar($order->object_id, $order->user_id, $order->days);
        }

        if (!empty($order->points))
        {
            $user = User::where('id', $order->user_id)->first();

            $user->active_points -= $order->points;

            $user->save();
        }

        if (!empty($order->ref_id))
        {
            $referralLink = ReferralLink::where('ref_id', $order->ref_id)->first();
    
            if (!empty($referralLink))
            {
                $referralLink->sum = $referralLink->sum + $order->price;
            }
        }
        
        $this->ChechIntive($order);
        
        broadcast(new PaymentNotification($order->user_id, $order->status));
    }

    public function CreditNotification(Request $request)
    {
        if ($request->status != "signed")
        {
            return;
        }

        $orderId = $request->id;
        $order = Order::where('order_id', $orderId)->first();
        $order->status = 'SIGNED';

        $order->save();

        if (!empty($order->ref_id))
        {
            $referralLink = ReferralLink::where('ref_id', $order->ref_id)->first();

            if (!empty($referralLink))
            {
                $referralLink->sum = $referralLink->sum + $order->price;
                $referralLink->requests = $referralLink->requests + 1;
            }
        }

        $this->ChechIntive($order);

        $this->AddAccessCourse($order->object_id, $order->user_id, $order->access, $order->days);

        broadcast(new PaymentNotification($order->user_id, 'CONFIRMED'));
    }


    public function AddAccessCourse($courseId, $userId, $access, $days)
    {
        $blocks = CourseBlock::where('course_id', $courseId)->orderBy('index')->get();

        $count = 0;

        foreach($blocks as $block)
        {
            if ($count >= $access && $access != 0)
            {
                break;
            }

            if (!empty(BlockAccess::where([['user_id', $userId], ['block_id', $block->id], ['course_id', $courseId]])->first()))
            {
                continue;
            }

            $count++;

            $accessModel = new BlockAccess();

            $accessModel->user_id = $userId;
            $accessModel->block_id = $block->id;
            $accessModel->course_id = $courseId;

            $accessModel->save();
        }

        if ($days <= 0)
        {
            return;
        }

        $courseAccess = CourseAccess::where([['user_id', $userId], ['course_id', $courseId]])->first();

        if (empty($courseAccess))
        {
            $courseAccess = new CourseAccess();

            $courseAccess->user_id = $userId;
            $courseAccess->course_id = $courseId;
        }

        $courseAccess->deadline = Carbon::now()->addDays($days)->translatedFormat('Y-m-d H:i:s');

        $courseAccess->save();
    }

    public function AddAccessWebinar($webinarId, $userId, $days)
    {
        $access = WebinarAccess::where([['user_id', $userId], ['webinar_id', $webinarId]])->first();
        $webinar = Webinar::where('id', $webinarId)->first();

        if (!empty($access))
        {
            return;
        }

        $access = new WebinarAccess();

        $access->user_id = $userId;
        $access->webinar_id = $webinarId;

        if ($days != 0)
        {
            $access->deadline = Carbon::now()->addDays($days);
        }

        $access->save();
    }

    public function CourseOrderCreate(Request $request, $courseId)
    {
        $request->validate([
            'price' => ['required'],
            'access' => ['required'],
            'access_days' => ['required'],
            'packet_name' => ['required'],
            'order_id' => ['required']
        ]);

        $courseName = Course::where('id', $courseId)->first()->name;
        $packetName = $request->packet_name;
        $userId = Auth::user()->id;

        $order = Order::where([['user_id', $userId], ['type', 'course'], ['object_id', $courseId]])->whereIn('status', ['INIT', 'VISIT'])->first();

        if (empty($order))
        {
            $order = new Order();

            $order->user_id = $userId;
            $order->order_id = time();
            $order->type = 'course';
            $order->object_id = $courseId;
        }

        $order->days = $request->access_days;
        $order->access = $request->access;
        $order->status = 'APPROVED';
        $order->price = $request->price;
        $order->packet = $packetName;

        $order->save();
    }

    public function SendJuricticNotification(Request $request, $courseId)
    {
        $courseName = Course::where('id', $courseId)->first()->name;

        $companyName = empty($request->company_name) ? '-' : $request->company_name;
        $inn = empty($request->inn) ? '-' : $request->inn;
        $ogrn = empty($request->ogrn) ? '-' : $request->ogrn;
        $account = empty($request->account) ? '-' : $request->account;
        $address = empty($request->address) ? '-' : $request->address;
        $tariff = empty($request->tariff) ? '-' : $request->tariff;

        $user = Auth::user();

        $userName = $user->name.' '.$user->last_name;
        $userId = $user->id;

        $html = "
        <h1>Заявка на покупку курса $courseName</h1>
        <p><b>Пользователь:</b> $userName (ID: $userId)</p>
        <p><b>Тариф:</b> $tariff</p>
        <p><b>Название компании:</b> $companyName</p>
        <p><b>ИНН:</b> $inn</p>
        <p><b>ОГРН:</b> $ogrn</p>
        <p><b>Рассчетный счет:</b> $account</p>
        <p><b>Адрес:</b> $address</p>
        ";

        $email = [
            'from_email' => 'info@kathedra.ru',
            'from_name' => 'Образовательная платформа',
            'to' => 'info@kathedra.ru',
            'subject' => 'Заявка на покупку курса',
            'text' => 'Заявка',
            'html' => $html,
            'payment' => "subscriber_priority",
        ];

        $refId = $request->cookie('ref_id');
        $referralLink = ReferralLink::where('ref_id', $refId)->first();

        if (!empty($referralLink))
        {
            $referralLink->requests = $referralLink->requests + 1;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('notisend.key')
        ])->withOptions([
            'verify' => false,
        ])->post('https://api.notisend.ru/v1/email/messages', $email);

        $juricticRequest = new JuricticRequest();

        $juricticRequest->user_id = Auth::user()->id;
        $juricticRequest->type = 'course';
        $juricticRequest->object_id = $courseId;

        $juricticRequest->save();
    }

    public function SendWebinarJuricticNotification(Request $request, $webinarId)
    {
        $webinarName = Course::where('id', $courseId)->first()->name;

        $companyName = empty($request->company_name) ? '-' : $request->company_name;
        $inn = empty($request->inn) ? '-' : $request->inn;
        $ogrn = empty($request->ogrn) ? '-' : $request->ogrn;
        $account = empty($request->account) ? '-' : $request->account;
        $address = empty($request->address) ? '-' : $request->address;
        $tariff = empty($request->tariff) ? '-' : $request->tariff;

        $user = Auth::user();

        $userName = $user->name.' '.$user->last_name;
        $userId = $user->id;

        $html = "
        <h1>Заявка на покупку вебинара $webinarName</h1>
        <p><b>Пользователь:</b> $userName (ID: $userId)</p>
        <p><b>Вебинар:</b> $webinarName</p>
        <p><b>Название компании:</b> $companyName</p>
        <p><b>ИНН:</b> $inn</p>
        <p><b>ОГРН:</b> $ogrn</p>
        <p><b>Рассчетный счет:</b> $account</p>
        <p><b>Адрес:</b> $address</p>
        ";

        $email = [
            'from_email' => 'info@kathedra.ru',
            'from_name' => 'Образовательная платформа',
            'to' => 'info@kathedra.ru',
            'subject' => 'Заявка на покупку курса',
            'text' => 'Заявка',
            'html' => $html,
            'payment' => "subscriber_priority",
        ];

        $refId = $request->cookie('ref_id');
        $referralLink = ReferralLink::where('ref_id', $refId)->first();

        if (!empty($referralLink))
        {
            $referralLink->requests = $referralLink->requests + 1;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('notisend.key')
        ])->withOptions([
            'verify' => false,
        ])->post('https://api.notisend.ru/v1/email/messages', $email);

        $juricticRequest = new JuricticRequest();

        $juricticRequest->user_id = Auth::user()->id;
        $juricticRequest->type = 'webinar';
        $juricticRequest->object_id = $webinarId;

        $juricticRequest->save();
    }

    public function CheckAccessWebinar(Request $request, $webinarId)
    {
        $userId = Auth::user()->id;

        if (Auth::user()->role == 'admin' || Auth::user()->role == 'moderator')
        {
            return true;
        }

        $access = WebinarAccess::where([['user_id', $userId], ['webinar_id', $webinarId]])->first();

        if (empty($access))
        {
            return false;
        }

        return !Carbon::parse($access->deadline)->addDays(1)->isPast();        
    }

    public function OrdersStatistic(Request $request)
    {
        $orders = Order::get();
        $result = [];

        $statusMap = [
            'INIT' => 'Создан',
            'NEW' => 'Создан',
            'FORM_SHOWED' => 'Платежная форма открыта покупателем',
            'DEADLINE_EXPIRED' => 'Платежная сессия закрыта в связи с превышением срока отсутствия активности по текущему статусу',
            'CANCELED' => 'Отменен',
            'PREAUTHORIZING' => 'Проверка платежных данных',
            'AUTHORIZING' => 'Резервируется',
            'AUTH_FAIL' => 'Не прошел авторизацию',
            'REJECTED' => 'Отклонен',
            '3DS_CHECKING' => 'Проверяется по протоколу 3-D Secure',
            '3DS_CHECKED' => 'Проверен по протоколу 3-D Secure',
            'PAY_CHECKING' => 'Платеж обрабатывается',
            'AUTHORIZED' => 'Зарезервирован',
            'REVERSING' => 'Резервирование отменяется',
            'PARTIAL_REVERSED' => 'Резервирование отменено частично',
            'REVERSED' => 'Резервирование отменено',
            'CONFIRMING' => 'Подтверждается',
            'CONFIRM_CHECKING' => 'Платеж обрабатывается',
            'CONFIRMED' => 'Оплачен',
            'REFUNDING' => 'Возвращается',
            'PARTIAL_REFUNDED' => 'Возвращен частично',
            'REFUNDED' => 'Возвращен полностью',
            'APPROVED' => 'Заявка на кредит рассмотрена',
            'SUCCESS' => 'Кредит оформлен',
            'VISIT' => 'Посещение страницы'
        ];

        foreach ($orders as $order)
        {
            $type = $order->type == 'course' ? 'Курс' : 'Вебинар';
            $name = '-';

            if ($order->type == 'course')
            {
                $course = Course::where('id', $order->object_id)->first();

                if (!empty($course))
                {
                    $name = $course->name;
                }
            }

            if ($order->type == 'webinar')
            {
                $webinar = Webinar::where('id', $order->object_id)->first();

                if (!empty($webinar))
                {
                    $name = $webinar->name;
                }
            }

            $user = User::where('id', $order->user_id)->first();

            if (empty($user))
            {
                $user = [
                    'id' => 0,
                    'name' => 'Пользователь удален',
                    'email' => '-',
                ];
            }
            else 
            {
                $user = [
                    'id' => $user->id,
                    'name' => $user->name.' '.$user->last_name,
                    'email' => $user->email,
                ];
            }

            $result[] = [
                'user' => $user,
                'status' => $statusMap[$order->status],
                'price' => $order->price,
                'packet' => $order->packet,
                'date' => Carbon::parse($order->created_at)->translatedFormat('d.m.Y H:i'),
                'name' => $name,
                'type' => $type,
            ];
        }

        $sortDir = $request->filter['sortDir'];

        if (!empty($sortDir))
        {
            usort($result, function($a, $b) use ($sortDir){

                if ($sortDir == 'asc')
                {
                    return Carbon::parse($a['date'])->timestamp >= Carbon::parse($b['date'])->timestamp;
                }

                if ($sortDir == 'desc')
                {
                    return Carbon::parse($a['date'])->timestamp <= Carbon::parse($b['date'])->timestamp;
                }
            });
        }

        return $result;
    }

    public function OrderInit(Request $request, $type, $objectId)
    {
        $order = Order::where([['user_id', Auth::user()->id], ['type', $type], ['object_id', $objectId]])->whereIn('status', ['INIT', 'VISIT'])->first();

        if (empty($order))
        {
            $order = new Order();

            $order->user_id = Auth::user()->id;
            $order->order_id = time();
            $order->type = $type;
            $order->object_id = $objectId;
        }

        
        $order->days = 0;
        $order->access = empty($request->access) ? 0 : $request->access;
        $order->status = 'VISIT';
        $order->price = 0;
        $order->packet = '-';

        $order->save();
    }


    private function ChechIntive($order)
    {
        $user = User::where('id', $order->user_id)->first();

        if ($user->invite_user != 0)
        {
            $inviteUser = User::where('id', $user->invite_user)->first();
            $inviteUser->active_points += $order->price * Setting::where('key', 'invite_percent')->first()->value / 100;
            $inviteUser->points += $order->price * Setting::where('key', 'invite_percent')->first()->value / 100;
        }
    }

    private function UsePoints($price)
    {
        $user = User::where('id', Auth::user()->id)->first();

        $residue = $price - $user->active_points;
        $newPrice = $price;

        if ($residue > 0)
        {
            //$user->active_points = 0;
            $newPrice = $residue;
        }
        else 
        {
            $user->active_points -= $price;
            $newPrice = 0;
        }

        $user->save();

        return $newPrice;
    }    
}
