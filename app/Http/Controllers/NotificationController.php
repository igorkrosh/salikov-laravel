<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Notification;

use App\Events\ClientNotification;

class NotificationController extends Controller
{
    public function NotificationList(Request $request)
    {
        return Notification::where('user_id', Auth::user()->id)->get();
    }

    public function CreateNotification($userId, $title, $text)
    {
        $notification = new Notification();

        $notification->user_id = $userId;
        $notification->title = $title;
        $notification->text = $text;

        $notification->save();

        broadcast(new ClientNotification($notification->user_id, $notification->title, $notification->text));
    }

    public function DeleteNotification(Request $request, $notificationId)
    {
        return Notification::where([['user_id', Auth::user()->id], ['id', $notificationId]])->delete();
    }

    public function DeleteAllNotification(Request $request)
    {
        return Notification::where([['user_id', Auth::user()->id]])->delete();
    }

    public function NotificationTest(Request $request)
    {
        broadcast(new ClientNotification(Auth::user()->id, 'title', 'text'));
    }
}
