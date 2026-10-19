<?php

namespace All1\LuModels\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * The typed event behind {model}.created / .updated / .deleted: the record, the input that caused the
 * write and the transport it came through. Broadcast when lu::models.broadcast.enabled is on, as
 * "lu-models.{action}" on the owner's private channel (App.Models.User.{user_id}) when the record
 * has a user_id, else on the record's own private channel (lu-models.{table}.{key}); the payload
 * never includes hidden attributes.
 */
abstract class ModelEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var Model */
    public $model;

    /** @var array<string, mixed> */
    public $data;

    /** @var string|null */
    public $controller_type;

    /**
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

    /** created|updated|deleted */
    abstract public function action(): string;

    public function broadcastWhen(): bool
    {
        return (bool) config('lu::models.broadcast.enabled', false);
    }

    public function broadcastAs(): string
    {
        return 'lu-models.'.$this->action();
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        if ($pattern = config('lu::models.broadcast.channel')) {
            return [new PrivateChannel(Str::of($pattern)
                ->replace('{table}', $this->model->getTable())
                ->replace('{key}', (string) $this->model->getKey())
                ->replace('{user_id}', (string) ($this->model->getAttribute('user_id') ?? ''))
                ->toString())];
        }

        $owner = $this->model->getAttribute('user_id');
        if ($owner !== null) {
            return [new PrivateChannel('App.Models.User.'.$owner)];
        }

        return [new PrivateChannel('lu-models.'.$this->model->getTable().'.'.$this->model->getKey())];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $attributes = $this->model->attributesToArray();

        return [
            'action' => $this->action(),
            'model' => get_class($this->model),
            'key' => $this->model->getKey(),
            'attributes' => $this->action() === 'deleted' ? ['id' => $this->model->getKey()] : $attributes,
            'changes' => $this->action() === 'updated' ? array_intersect_key($attributes, $this->model->getChanges()) : [],
            'transport' => $this->controller_type,
        ];
    }
}
