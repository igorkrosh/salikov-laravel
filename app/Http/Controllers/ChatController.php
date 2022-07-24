<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\ChatMessage;
use App\Models\User;

use App\Events\StreamChatUpdate;
use App\Events\StreamChatDeleteMessage;

class ChatController extends Controller
{
    public function SendMessage(Request $request, $type, $streamId)
    {
        $message = new ChatMessage();
        $user = User::where('id', Auth::user()->id)->first();

        $message->user_id = $user->id;
        $message->stream_id = $streamId;
        $message->type = $type;
        $message->text = $request->text;

        $message->save();

        $avatar = empty($user->img_path) ? '' : url('/').'/'.$user->img_path;
        $username = $user->name.' '.$user->last_name;

        broadcast(new StreamChatUpdate($avatar, $username, $message->text, $message->created_at, $user->role, $type, $streamId, $message->id));
    }

    public function GetChatMessages(Request $request, $type, $streamId)
    {
        $messages = ChatMessage::where([['type', $type], ['stream_id', $streamId]])->get();
        $response = [];

        foreach($messages as $message)
        {
            $user = User::where('id', $message->user_id)->first();
            $response[] = [
                'id' => $message->id,
                'avatar' => empty($user->img_path) ? '' : url('/').'/'.$user->img_path,
                'username' => $user->name.' '.$user->last_name,
                'text' => $message->text,
                'date' => $message->created_at,
                'role' => $user->role,
                'type' => $type,
                'streamId' => $streamId
            ];
        }

        return $response;
    }

    public function DeleteMessage(Request $request, $type, $streamId, $messageId)
    {
        $message = ChatMessage::where([['type', $type], ['stream_id', $streamId], ['id', $messageId]])->delete();

        broadcast(new StreamChatDeleteMessage($messageId, $type, $streamId));
    }
}
