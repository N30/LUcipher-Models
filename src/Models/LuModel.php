<?php

namespace All1\LuModels\Models;

use All1\LuModels\Services\LuModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class LuModel extends Model
{
    /** @var list<string> */
    public $index_columns = ['id', 'created_at'];

    // these are removed from the API and also views from display
    /** @var list<string> */
    public $hidden_columns = [];

    // Reminder that I am making this very unsecure specially with the modified query builder that allows all fields
    // so take necessary messures to secure this when and if it requires building models that are not read all and have sensitive columns
    // you can defined for all the xtra joins
    /** @var array<string, list<string>> */
    public $index_relationships_columns = [
        /*  'relationship_name' => ['table_name.id', 'table_name.column_name'], and so on next row */
    ];

    /** @var list<string> */
    public $default_search_columns;

    // default index sort column
    /** @var string */
    public $index_default_sort = 'id';

    /** @var string */
    public $index_default_order = 'DESC';

    // if not defined all relationships will be allowed as LuModel will get all 2 depth relationships
    // by detaul added, you can add as many as you want but for security reasons only ones whitelisted in index_columns show in resutls
    /** @var list<string>|null */
    public $default_loaded_relationships;

    // needed for API assignment of these fields/whatever user can manipulate
    /** @var string */
    public $action_folder = 'CRUD';

    // safety features for sensitive models that we never want hacked
    // creation safety uses create() with respect to $fillable
    /** @var bool */
    public $creation_safety = true;

    // update safety uses fill() with respect to $fillable
    /** @var bool */
    public $update_safety = true;

    /** @var bool */
    public $read_safety = true;

    /** @var bool */
    public $delete_safety = true;

    // allow public access to pages linke index, this can also be set in the config file globally for models
    // however if policy exists it will override this
    /** @var list<string> */
    public $public_pages = []; // example: ['index', 'show'];

    /** @var string|null */
    public $after_save_redirect; // show, edit, index, create

    /** @var string|null */
    public $after_destroy_redirect; // show, edit, index, create

    // highly recommended to keep these off 99% of time if public needs access should be encrypted IDs
    // THE ONLY REASON I DIDN"T ENFORCE THIS WITHOUT AN OPTION IS FOR USAGE WHERE ANY SECURITY CONCERN IS RIDICULOUS
    // ALSO KEEP IN MIND THIS IS JUST FOR lu::web.resources ROUTES THAT ARE VERY DYNAMIC YOU CAN USE SLUGS WHERE IT MAKES SENSE IN PUBLIC CONTROLLERS
    /** @var bool */
    public $allow_public_access_by_id = false; // for publicly accessible routes that cant be controlled by policy may be security concern

    /** @var bool */
    public $allow_public_access_by_slug = false; // same for slugs though slight depending on model harder to guess

    /**
     * Let a guest name a record by plain key or slug (both opt-ins above at once). A signed-in caller
     * may always use a plain key: the policy decides what they reach (4.0; 3.0.x refused it).
     *
     * @var bool
     */
    public $allow_any_unencrypted_access = false; // SECURE THE FORT DOWN AND GIVE EVERYTHING AN ID

    /**
     * @deprecated 4.0 misspelling of $allow_any_unencrypted_access; still read as an alias
     *
     * @var bool
     */
    public $allow_any_unencrypred_access = false;

    /**
     * @param  string  $column
     * @return mixed
     */
    public function displayText($column)
    {
        $data = $this->$column;

        return is_iterable($data) ? (string) json_encode($data) : $data;
    }

    /** @return list<string> */
    public function getModelRelationships()
    {
        $model = $this;
        $relationships = [];

        $reflector = new ReflectionClass($model);

        foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Only check methods defined in the model (not inherited)
            if ($method->class === get_class($model) && $method->getNumberOfParameters() === 0) {
                try {
                    $result = $method->invoke($model);

                    if ($result instanceof Relation) {
                        $relationships[] = $method->getName();
                    }
                } catch (\Throwable $e) {
                    // Ignore methods that throw errors (non-relationship methods)
                }
            }
        }

        return $relationships;
    }

    /** @return array<class-string, array<string, mixed>> */
    public static function getModels()
    {

        $modelPath = config('lu::models.models_path') ?: app_path('Models');
        if (! is_dir($modelPath)) {
            return [];
        }
        $response = [];
        foreach (\File::allFiles($modelPath) as $file) {
            $model = self::getNamespaceFromFile($file).'\\'.pathinfo($file, PATHINFO_FILENAME);
            // the name relative to the models root (Admin\User for app/Models/Admin/User.php), whatever the namespace
            $relative = str_replace('/', '\\', substr($file->getRelativePathname(), 0, -strlen('.'.$file->getExtension())));

            $meta = self::getMetaData($model, $relative);
            if (isset($meta['abstract']) && $meta['abstract']) {
                continue;
            }
            $response[$model] = $meta;
        }

        return $response;
    }

    /**
     * A model's name relative to the models root, which names its route slug and view folder:
     * App\Models\Admin\User is Admin\User. A model discovered under models_path is known by its path;
     * any other model outside App\Models goes by its basename.
     */
    public static function relativeName(string $class): string
    {
        $class = ltrim($class, '\\');
        if (str_starts_with($class, 'App\\Models\\')) {
            return substr($class, strlen('App\\Models\\'));
        }
        try {
            $discovered = self::getModels(); // needs an application (config, files); a bare container has none
        } catch (\Throwable) {
            $discovered = [];
        }
        foreach ($discovered as $known => $meta) {
            if ($known === $class && isset($meta['relative'])) {
                return $meta['relative'];
            }
        }

        return class_basename($class);
    }

    /**
     * @param  \SplFileInfo|string  $file
     * @return string|null
     */
    public static function getNamespaceFromFile($file)
    {
        $fileContents = file_get_contents($file);
        if (preg_match('/namespace\s+([a-zA-Z0-9_\\\]+)/', $fileContents, $matches)) {
            return $matches[1]; // Return the matched namespace
        }

        return null; // Return null if no namespace is found
    }

    // v3.0: filters were inverted in 2.x (getLuModels returned NON-lu models and vice versa) — fixed to match their names
    /** @return array<class-string, array<string, mixed>> */
    public static function getLuModels()
    {
        $response = self::getModels();
        foreach ($response as $key => $model) {
            if (! $model['extends_lu_model']) {
                unset($response[$key]);
            }
        }

        return $response;
    }

    /** @return array<class-string, array<string, mixed>> */
    public static function getNonLuModels()
    {
        $response = self::getModels();
        foreach ($response as $key => $model) {
            if ($model['extends_lu_model']) {
                unset($response[$key]);
            }
        }

        return $response;
    }

    /**
     * @param  string  $model
     * @return array<string, mixed>
     */
    public static function getMetaData($model, ?string $relative = null)
    {
        $relative_namespace = $relative ?? self::relativeName((string) $model);

        $model_name = class_basename($model);
        $model_object = new $model;
        // make model name lower case and only alphanumberic
        $check = strtolower(str_replace(' ', '', ucwords(preg_replace('/[^A-Za-z0-9]/', ' ', $model_name))));

        if (in_array($check, ['lumodel', 'base'])) {
            return [
                'namespace' => $model,
                'obj' => new $model_object,
                'abstract' => true,
            ];
        }

        // place space in camel format string
        $friendly_name = preg_replace('/\B([A-Z])/', ' $1', $model_name);

        $implements_lumodel = (is_subclass_of($model_object, LuModel::class));
        $table = $model_object->getTable();
        $route_group = str_replace('wozxzow', '~', Str::slug(str_replace('\\', 'WOZXZOW', $relative_namespace)));
        $view_folder = str_replace('\-', '.', Str::kebab($relative_namespace));
        // $routeName = 'lu::web.resources'; // Example: 'users.index'

        $routes = [];
        foreach (['web', 'api', 'spa', 'rest'] as $route_type) {

            foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'] as $action) {
                $routeName = 'lu::'.$route_type.'.resources';
                // check if route exists
                if (! \Route::has($routeName)) {
                    continue;
                }
                $route = route($routeName, ['model' => $route_group, 'action' => $action]);

                $routes[$route_type][$action] = [
                    'url' => $route,
                    'method' => match ($action) {
                        'index' => 'GET',
                        'create' => 'GET',
                        'store' => 'POST',
                        'show' => 'GET',
                        'edit' => 'GET',
                        'update' => 'PUT',
                        'destroy' => 'DELETE',
                    },
                    'dynamic' => match ($action) {
                        'show' => true,
                        'edit' => true,
                        'update' => true,
                        'destroy' => true,
                        default => false
                    },
                ];

            }
        }

        return [
            'namespace' => $model,
            'obj' => new $model_object,
            'table' => $table,
            'name' => $friendly_name,
            'headline' => Str::headline($friendly_name),
            'camel' => Str::camel($friendly_name),
            'routes' => $routes,
            'route_group' => $route_group,
            'view_folder' => $view_folder,
            'extends_lu_model' => $implements_lumodel,
            'relative' => $relative_namespace,
        ];
    }

    /**
     * @param  string  $action
     * @param  LuModels  $service
     * @param  array<string, mixed>  $data
     * @param  string|null  $controller_type
     * @return LuModels
     */
    public function fromData($action, $service, $data, $controller_type)
    {
        // this seemingly redundant syntax way of writing this is the only way I was able to get it to work preserving the model without turning it inot parent model LuModel
        $x = new LuModels;

        return $x->fromData($action, $service, $this, $data, $controller_type);
    }

    /** @return string */
    public function viewFolder()
    {
        return static::viewFolderFor($this);
    }

    /**
     * @param  string  $action
     * @return string
     */
    public function viewFile($action)
    {
        return static::viewFileFor($this, $action);
    }

    /**
     * The app view folder for a model (`resources/views/{plural-lowercase}`) when it exists, else the
     * package's web views. Works for any Eloquent model, not only LuModel subclasses.
     */
    public static function viewFolderFor(Model $model): string
    {
        $potential_folder = strtolower(Str::plural(class_basename($model)));

        return File::exists(resource_path('views/'.$potential_folder)) ? $potential_folder : 'lu_models::web';
    }

    /** The view for $action: the app's `{folder}.{action}` when it exists, else the package's. */
    public static function viewFileFor(Model $model, string $action): string
    {
        $candidate = static::viewFolderFor($model).'.'.$action;

        return view()->exists($candidate) ? $candidate : 'lu_models::web.'.$action;
    }
}
