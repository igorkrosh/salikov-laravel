<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

use DateTime;
use DateTimeZone;

use App\Models\Course;
use App\Models\CourseBlock;
use App\Models\BlockAccess;
use App\Models\CourseAccess;
use App\Models\Order;

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

        $courseName = Course::where('id', $courseId)->first()->name;
        $packetName = $request->packet_name;
        $userId = Auth::user()->id;

        $amount = $request->price * 100;
        $desc = "Покупка доступа к курсу \"$courseName\" ($packetName)";
        $orderId = time();

        $orderData = [
            'TerminalKey' => config('tinkoff.terminal_key'),
            'Amount' => $amount,
            'Description' => $desc,
            'OrderId' => $orderId,
            'NotificationURL' => url('/')."/api/buy/order/notification",
            'DATA' => [
                'accces' => 5
            ]
        ];

        $order = new Order();

        $order->user_id = $userId;
        $order->order_id = $orderId;
        $order->days = $request->access_days;
        $order->access = $request->access;
        $order->status = 'INIT';
        $order->price = $request->price;
        $order->course_id = $courseId;
        $order->packet = $packetName;

        $order->save();

        $response = Http::withOptions([
            'verify' => false,
        ])->post('https://securepay.tinkoff.ru/v2/Init', $orderData);

        $data = $response->object();
        $data->hash = $this->MakeHash($orderData);

        return $data;
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

        $this->AddAccess($order->course_id, $order->user_id, $order->access, $order->days);

        broadcast(new PaymentNotification($order->user_id, $order->status));
    }

    public function CreditNotification(Request $request, $userId)
    {
        broadcast(new PaymentNotification($userId, $request->all()));
    }


    public function AddAccess($courseId, $userId, $access, $days)
    {
        $blocks = CourseBlock::where('course_id', $courseId)->orderBy('index')->get();

        $count = 0;

        foreach($blocks as $block)
        {
            $count++;

            if ($count > $access && $access != 0)
            {
                break;
            }

            if (!empty(BlockAccess::where([['user_id', $userId], ['block_id', $block->id], ['course_id', $courseId]])->first()))
            {
                continue;
            }


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

    private function MakeHash($orderData)
    {
        $orderData['Password'] = config('tinkoff.terminal_password');
        ksort($orderData);
        $hashString = '';

        foreach($orderData as $key => $value)
        {
            if ($key == 'Shops' || $key == 'Receipt' || $key == 'DATA')
            {
                continue;
            }

            $hashString .= $value;
        }

        return hash('sha256', $hashString);

    }
    
}
