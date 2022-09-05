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

        $orderData = [
            'TerminalKey' => config('tinkoff.terminal_key'),
            'Amount' => $amount,
            'Description' => $desc,
            'OrderId' => $orderId,
            'NotificationURL' => url('/')."/api/buy/order/notification",
        ];

        $order = new Order();

        $order->user_id = $userId;
        $order->order_id = $orderId;
        $order->type = 'course';
        $order->days = $request->access_days;
        $order->access = $request->access;
        $order->status = 'INIT';
        $order->price = $orderPrice;
        $order->object_id = $courseId;
        $order->packet = $packetName;
        $order->promocode = empty($request->promocode) ? '' : $request->promocode;
        $order->points = empty($request->use_points) ? 0 : Auth::user()->active_points;

        $order->save();

        $redId = $request->cookie('ref_id');
        $referralLink = ReferralLink::where('ref_id', $redId)->first();

        if (!empty($referralLink))
        {
            $referralLink->requests = $referralLink->requests + 1;
        }

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

        $order->save();

        $redId = $request->cookie('ref_id');
        $referralLink = ReferralLink::where('ref_id', $redId)->first();

        if (!empty($referralLink))
        {
            $referralLink->requests = $referralLink->requests + 1;
        }

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

        $orderData = [
            'TerminalKey' => config('tinkoff.terminal_key'),
            'Amount' => $amount,
            'Description' => $desc,
            'OrderId' => $orderId,
            'NotificationURL' => url('/')."/api/buy/order/notification",
        ];

        $access = WebinarAccess::where([['user_id', $userId], ['webinar_id', $webinarId]])->first();

        if (!empty($access))
        {
            return response()->json([
                'message' => 'У вас уже есть доступ к этому вебинару'
            ] , 422);
        }

        $order = new Order();

        $order->user_id = $userId;
        $order->order_id = $orderId;
        $order->type = 'webinar';
        $order->days = $request->access_days;
        $order->access = 0;
        $order->status = 'INIT';
        $order->price = $request->price;
        $order->object_id = $webinarId;
        $order->packet = $request->packet_name;

        $order->save();

        $redId = $request->cookie('ref_id');
        $referralLink = ReferralLink::where('ref_id', $redId)->first();

        if (!empty($referralLink))
        {
            $referralLink->requests = $referralLink->requests + 1;
        }

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

        $refId = $request->cookie('ref_id');
        $referralLink = ReferralLink::where('ref_id', $refId)->first();

        if (!empty($referralLink))
        {
            $referralLink->sum = $referralLink->sum + $order->price;
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

        $refId = $request->cookie('ref_id');
        $referralLink = ReferralLink::where('ref_id', $refId)->first();

        if (!empty($referralLink))
        {
            $referralLink->sum = $referralLink->sum + $order->price;
            $referralLink->requests = $referralLink->requests + 1;
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

        $courseAccess = new CourseAccess();

        $courseAccess->user_id = $userId;
        $courseAccess->course_id = $courseId;

        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('Europe/Moscow'));
        $dt->setTimestamp(time() + $days * 24 * 60 * 60);

        $courseAccess->deadline = $dt->format('Y-m-d H:i:s');

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
        $access->deadline = Carbon::parse($webinar->date_start)->addDays($days);

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

        $order = new Order();

        $order->user_id = $userId;
        $order->order_id = $request->order_id;
        $order->days = $request->access_days;
        $order->access = $request->access;
        $order->status = 'APPROVED';
        $order->price = $request->price;
        $order->course_id = $courseId;
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
        $access = WebinarAccess::where([['user_id', $userId], ['webinar_id', $webinarId]])->first();

        if (empty($access))
        {
            return false;
        }

        return !Carbon::parse($access->deadline)->addDays(1)->isPast();        
    }


    private function ChechIntive($order)
    {
        $user = User::where('id', $order->user_id)->first();

        if ($user->invite_user != 0)
        {
            $inviteUser = User::where('id', $user->invite_user)->first();
            $inviteUser->active_points += $order->price * Setting::where('key', 'invite_percent')->first() / 100;
            $inviteUser->points += $order->price * Setting::where('key', 'invite_percent')->first() / 100;
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
