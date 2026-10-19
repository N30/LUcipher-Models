<?php

namespace All1\LuModels\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated 3.1 A read is not a state change; this event still fires for listeners that expect it and
 *             goes away in 4.0. It is never broadcast.
 */
class Read
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var Model */
    public $model;

    /** @var array<string, mixed> */
    public $data;

    /** @var string|null */
    public $controller_type;

    /**
     * Create a new event instance.
     *
     * @param  Model  $model
     * @param  array<string, mixed>  $data
     * @param  string|null  $controller_type
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
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
