<?php

namespace All1\LuModels\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Created
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
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        // Phase 4 (study §5.10) gives these events real channels; until then nothing is broadcast.
        return [];
    }
}
