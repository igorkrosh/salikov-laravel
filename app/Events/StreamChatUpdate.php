<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamChatUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */

    public $avatar;
    public $username;
    public $text;
    public $date;
    public $role;
    public $type;
    public $streamId;
    public $id;

    public function __construct($avatar, $username, $text, $date, $role, $type, $streamId, $id)
    {
        $this->avatar = $avatar;
        $this->username = $username;
        $this->text = $text;
        $this->date = $date;
        $this->role = $role;
        $this->type = $type;
        $this->streamId = $streamId;
        $this->id = $id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('stream-chat-'.$this->type.'.'.$this->streamId);
    }
}
