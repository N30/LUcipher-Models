<?php

namespace All1\LuModels\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Updated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $model;
    public $data;
    public $controller_type;
    /**
     * Create a new event instance.
     */
    public function __construct($model, $data, $controller_type)
    {
        $this->model = $model;  
        $this->data = $data;
        $this->controller_type = $controller_type;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        
    }
}
