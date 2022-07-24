<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

use App\Events\ChatUpdate;

class TicketController extends Controller
{
    public function CreateTicket(Request $request)
    {
        $ticket = new Ticket();

        $config = json_decode($request->input('ticket'));

        $ticket->title = $config->title;
        $ticket->text = $config->text;
        $ticket->user_id = Auth::user()->id;

        $ticket->save();
        
        $file = $request->file('file');

        if (!empty($file))
        {
            $path = app(FileController::class)->StoreTicketFile($file, $ticket->id);
            
            $ticket->file_path = $path;
            $ticket->save();
        }

        return $ticket;
    }

    public function GetUserTickets(Request $request)
    {
        $tickets = Ticket::where('user_id', Auth::user()->id)->get();
        $response = [];

        foreach($tickets as $ticket)
        {
            $response[] = [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'text' => $ticket->text,
                'data' => $ticket->created_at,
            ];
        }

        return $response;
    }

    public function GetTicketsList(Request $request)
    {
        $tickets = Ticket::get();
        $response = [];

        foreach($tickets as $ticket)
        {
            $response[] = [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'text' => $ticket->text,
                'data' => $ticket->created_at,
            ];
        }

        return $response;
    }

    public function GetTicketChat(Request $request, $ticketId)
    {
        $response = [];

        $ticket = Ticket::where('id', $ticketId)->first();
        $user = User::where('id', $ticket->user_id)->first();

        $response['ticket'] = [
            'user_id' => $ticket->user_id,
            'username' => $user->name.' '.$user->last_name,
            'avatar' => empty($user->img_path) ? '' : url('/').'/'.$user->img_path,
            'text' => $ticket->text,
            'title' => $ticket->title,
            'date' => $ticket->created_at,
            'file' => empty($ticket->file_path) ? '' : url('/').'/'.$ticket->file_path
        ];

        $messages = TicketMessage::where('ticket_id', $ticketId)->get();

        foreach($messages as $message)
        {
            $user = User::where('id', $message->user_id)->first();

            $response['messages'][] = [
                'user_id' => $message->user_id,
                'username' => $user->name.' '.$user->last_name,
                'avatar' => empty($user->img_path) ? '' : url('/').'/'.$user->img_path,
                'text' => $message->text,
                'date' => $message->created_at,
                'file' => empty($message->file_path) ? '' : url('/').'/'.$message->file_path
            ];
        }

        return $response;
    }

    public function AddMessageToChat(Request $request, $ticketId)
    {
        $ticket = Ticket::where('id', $ticketId)->first();
        $config = json_decode($request->input('message'));
        $message = new TicketMessage();

        $message->user_id = Auth::user()->id;
        $message->ticket_id = $ticket->id;
        $message->text = $config->text;

        $message->save();

        $file = $request->file('file');

        if (!empty($file))
        {
            $path = app(FileController::class)->StoreTicketFile($file, $ticket->id);
            
            $message->file_path = $path;
            $message->save();
        }

        if ($ticket->user_id != $message->user_id)
        {
            $chat = $ticket->title;
            app(NotificationController::class)->CreateNotification($ticket->user_id, 'Вы получили новый ответ', "Новый ответ на запрос \"$chat\"");
        }

        broadcast(new ChatUpdate($ticket->id));

        return $message;
    }
}
