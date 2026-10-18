<?php

namespace All1\LuModels\Providers;

// use Illuminate\Support\ServiceProvider;

use All1\LuUnite\Providers\LuUniteServiceProvider;
use Illuminate\Support\Facades\Blade;

// use ReflectionClass;
// use ReflectionException;

class LuModelsServiceProvider extends LuUniteServiceProvider
{
    /* didnt work as expected so manually added to boot
     protected $listen = [
         \All1\LuModels\Events\Created::class => [
             \All1\LuModels\Listeners\CreationAftermath::class,
         ],
     ];
     */

    public function register(): void
    {
        $this->lu_addon_provider_register($this, 'models', [
            'has_service' => true,
            //     'has_facade'=>true,
        ]);

        // I don't think I actually need this
        // Automatically bind all actions in the Actions folder
        /*foreach (glob(__DIR__ . '/../Actions/*.php') as $file) {
           $class = 'All1\\LuModels\\Actions\\' . pathinfo($file, PATHINFO_FILENAME);

           if (class_exists($class)) {
               $this->app->bind($class, function () use ($class) {
                   return new $class();
               });
           }
        }*/

    }

    public function boot(): void
    {

        $this->lu_addon_provider_boot($this, 'models', [
            'register_views' => true,
            'register_blade_components' => true,
        ]);

        // unite registers the class components under the alias "models"; every shipped blade writes
        // <x-lu_models::…>, so without this alias Blade fell back to anonymous components and the index
        // table never saw its $db_columns. Phase 2 moves all registration into this provider.
        Blade::componentNamespace('All1\\LuModels\\View\\Components', 'lu_models');

        $this->loadRoutesFrom(__DIR__.'/../../routes/lu_models-routes.php'); // or api.php

    }
}
