<?php

namespace All1\LuModels\Verbs;

use All1\LuModels\Actions\Action;
use All1\LuModels\Services\LuModels;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated 3.1 The five Verbs (Creation, Revelation, Updation, Deletion, Annexation) were the
 *             write half of each operation; the write now lives in Actions\{Create,Read,Update,Delete,Index}::perform().
 *             These classes stay one release as adapters so `App\Models\Verbs\{folder}\{Model}{Verb}`
 *             overrides and direct callers keep working. They were never event-sourced (study §4).
 */
abstract class Verb
{
    /** The Action class whose perform() this adapter delegates to. */
    protected const ACTION = Action::class;

    /** @var LuModels */
    public $service;

    /** @var Model */
    public $model;

    public $data;

    public $controller_type;

    public function __construct($service, $model, $data, $controller_type)
    {
        $this->service = $service;
        $this->model = $model;
        $this->data = $data;
        $this->controller_type = $controller_type;
    }

    public function handle()
    {
        $action = static::ACTION;
        $this->service = (new $action)->perform($this->service, $this->model, (array) $this->data, $this->controller_type);
        $this->model = $this->service->model instanceof Model ? $this->service->model : $this->model;

        return $this->service;
    }

    /** @deprecated 3.1 Use Actions\Action::appEventClass(). */
    public static function appEventClass($model, string $suffix): ?string
    {
        return Action::appEventClass($model, $suffix);
    }
}
