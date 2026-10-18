<?php

/*
 * Classes an app would define to override the package defaults for the Gadget model, at the
 * documented names. Loaded with require_once by the tests that need them.
 */

namespace App\LuModels\Actions\CRUD {
    class CreateGadget
    {
        public static array $calls = [];

        public static function run($service, $model, $data, $controller_type)
        {
            static::$calls[] = [$model, $data, $controller_type];

            return $service;
        }
    }
}

namespace App\Events\CRUD {
    class GadgetCreated
    {
        public function __construct(public $model, public $data, public $controller_type) {}
    }
}
