<?php

namespace All1\LuModels\Support;

use All1\LuModels\Actions\Action;
use All1\LuModels\Models\LuModel;
use All1\LuModels\Services\LuModels;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Everything the package knows about one model, gathered once and cached: the table and its
 * columns (type, nullable, default, help text from `public $lu_help` or the column comment, max length,
 * keys), what is fillable, what
 * is hidden, what each action may see or write ($lu_fields), the relationships, the exposed
 * transports, the policy, the validation rules per action, the resolved override classes and the
 * model's own options. The service, the blades, the API, the inspector and the MCP tools read this.
 *
 * Nothing in here varies within a deploy, so it is cached under lu_models.descriptor.{class} in the
 * configured store (lu::models.descriptor_cache) until Descriptor::forget()/flush() or cache:clear.
 */
final class Descriptor implements Arrayable
{
    public const ACTIONS = ['index', 'show', 'create', 'edit'];

    private const REGISTRY_KEY = 'lu_models.descriptors';

    /**
     * @param  array<string, array<string, mixed>>  $fields  name => description
     * @param  array<string, string|null>  $relationships  method => related class when resolvable
     * @param  list<string>  $transports
     * @param  array<string, array<string, mixed>>  $rules  action => rules
     * @param  array<string, array{class: string, source: string}>  $overrides  operation => resolved class
     * @param  array<string, mixed>  $options
     */
    private function __construct(
        public readonly string $class,
        public readonly string $table,
        public readonly string $key,
        public readonly string $name,
        public readonly string $headline,
        public readonly string $slug,
        public readonly bool $extendsLuModel,
        public readonly array $fields,
        public readonly array $relationships,
        public readonly array $transports,
        public readonly ?string $policy,
        public readonly array $rules,
        public readonly array $overrides,
        public readonly array $options,
    ) {}

    /** The cached descriptor for a model class or instance. */
    public static function for(string|Model $model): self
    {
        $class = ltrim(is_object($model) ? get_class($model) : $model, '\\');

        // default off: a container without the package config (plain unit tests) builds every time
        if (! config('lu::models.descriptor_cache.enabled', false)) {
            return self::build($class);
        }

        $store = Cache::store(config('lu::models.descriptor_cache.store'));
        $data = $store->rememberForever(self::cacheKey($class), function () use ($class, $store) {
            $registry = (array) $store->get(self::REGISTRY_KEY, []);
            $registry[$class] = true;
            $store->forever(self::REGISTRY_KEY, $registry);

            return self::build($class)->toArray();
        });

        return self::fromArray($data);
    }

    /** Build without touching the cache. */
    public static function build(string $class): self
    {
        $class = ltrim($class, '\\');
        /** @var Model $instance */
        $instance = new $class;
        $visibility = FieldVisibility::for($instance);

        $fields = [];
        foreach (LuModels::schemaColumns($instance->getTable(), $instance->getConnectionName()) as $column) {
            $name = $column->Field;
            $cleanType = preg_replace('/\s*\([^)]*\)/', '', (string) $column->Type);
            $length = preg_match('/\((\d+)/', (string) $column->Type, $m) ? (int) $m[1] : null;
            $visible = array_values(array_filter(self::ACTIONS, fn ($action) => $visibility->visible($name, $action)));

            $fields[$name] = [
                'name' => $name,
                'type' => $column->Type,
                'clean_type' => $cleanType,
                'input' => self::inputFor($cleanType),
                'nullable' => $column->Null === 'YES',
                'default' => $column->Default,
                'max_length' => $length,
                'comment' => (string) $column->Comment,
                'help' => (string) ($instance->lu_help[$name] ?? $column->Comment),
                'primary' => $column->Key === 'PRI',
                'unique' => $column->Key === 'UNI',
                'auto_increment' => $column->Extra === 'auto_increment',
                'fillable' => $instance->isFillable($name),
                'hidden' => in_array($name, $instance->getHidden(), true) || in_array($name, (array) ($instance->hidden_columns ?? []), true),
                'visible' => $visible,
                'writable' => array_values(array_filter(['create', 'edit'], fn ($action) => $visibility->writable($name, $action))),
            ];
        }

        $relationships = [];
        if (method_exists($instance, 'getModelRelationships')) {
            foreach ($instance->getModelRelationships() as $method) {
                try {
                    $relationships[$method] = get_class($instance->{$method}()->getRelated());
                } catch (\Throwable) {
                    $relationships[$method] = null;
                }
            }
        }

        // a bare container (plain unit tests) has no gate and no resource path; a Laravel app always does
        $policy = app()->bound(\Illuminate\Contracts\Auth\Access\Gate::class) ? Gate::getPolicyFor($instance) : null;
        $relative = implode('\\', array_slice(explode('\\', $class), 2));
        $basename = class_basename($class);
        $friendly = preg_replace('/\B([A-Z])/', ' $1', $basename);
        $folder = $instance->action_folder ?? 'CRUD';

        $overrides = [];
        foreach (['index' => 'Index', 'show' => 'Read', 'create' => 'Create', 'update' => 'Update', 'delete' => 'Delete'] as $operation => $default) {
            $resolved = ltrim(LuModels::actionClass($operation, $instance), '\\');
            $overrides[$operation] = [
                'class' => $resolved,
                'source' => str_starts_with($resolved, 'All1\\LuModels\\') ? 'package' : 'app',
            ];
        }
        foreach (['Created', 'Updated', 'Deleted'] as $suffix) {
            $app = Action::appEventClass($instance, $suffix);
            $overrides['event_'.strtolower($suffix)] = [
                'class' => ltrim($app ?? '\\All1\\LuModels\\Events\\'.$suffix, '\\'),
                'source' => $app ? 'app' : 'package',
            ];
        }
        $viewFolder = app()->bound('path.resources') ? LuModel::viewFolderFor($instance) : 'lu_models::web';
        $overrides['views'] = ['class' => $viewFolder, 'source' => $viewFolder === 'lu_models::web' ? 'package' : 'app'];
        $overrides['policy'] = ['class' => $policy ? get_class($policy) : '', 'source' => $policy ? 'app' : 'missing'];

        return new self(
            class: $class,
            table: $instance->getTable(),
            key: $instance->getKeyName(),
            name: $friendly,
            headline: Str::headline($friendly),
            slug: str_replace('wozxzow', '~', Str::slug(str_replace('\\', 'WOZXZOW', Str::plural($relative)))),
            extendsLuModel: $instance instanceof LuModel,
            fields: $fields,
            relationships: $relationships,
            transports: Exposure::transportsFor($instance),
            policy: $policy ? get_class($policy) : null,
            rules: [
                'create' => (array) ($instance->store_validation_rules ?? []),
                'update' => (array) ($instance->update_validation_rules ?? $instance->store_validation_rules ?? []),
            ],
            overrides: $overrides,
            options: [
                'action_folder' => $folder,
                'index_columns' => (array) ($instance->index_columns ?? []),
                'index_default_sort' => $instance->index_default_sort ?? $instance->getKeyName(),
                'index_default_order' => $instance->index_default_order ?? 'DESC',
                'default_search_columns' => (array) ($instance->default_search_columns ?? []),
                'default_loaded_relationships' => (array) ($instance->default_loaded_relationships ?? []),
                'public_pages' => (array) ($instance->public_pages ?? []),
                'creation_safety' => ($instance->creation_safety ?? true) !== false,
                'update_safety' => ($instance->update_safety ?? true) !== false,
                'allow_public_access_by_id' => (bool) ($instance->allow_public_access_by_id ?? false),
                'allow_public_access_by_slug' => (bool) ($instance->allow_public_access_by_slug ?? false),
                'allow_any_unencrypted_access' => (bool) ($instance->allow_any_unencrypred_access ?? false),
            ],
        );
    }

    public static function forget(string|Model $model): void
    {
        $class = ltrim(is_object($model) ? get_class($model) : $model, '\\');
        $store = Cache::store(config('lu::models.descriptor_cache.store'));
        $store->forget(self::cacheKey($class));
        $registry = (array) $store->get(self::REGISTRY_KEY, []);
        unset($registry[$class]);
        $store->forever(self::REGISTRY_KEY, $registry);
    }

    /** Drop every cached descriptor. */
    public static function flush(): void
    {
        $store = Cache::store(config('lu::models.descriptor_cache.store'));
        foreach (array_keys((array) $store->get(self::REGISTRY_KEY, [])) as $class) {
            $store->forget(self::cacheKey($class));
        }
        $store->forget(self::REGISTRY_KEY);
    }

    public static function cacheKey(string $class): string
    {
        return 'lu_models.descriptor.'.ltrim($class, '\\');
    }

    /** @return array<string, mixed>|null */
    public function field(string $name): ?array
    {
        return $this->fields[$name] ?? null;
    }

    /** @return array<string, array<string, mixed>> the fields $action may read (show/index) or write (create/edit) */
    public function fieldsFor(string $action): array
    {
        $action = FieldVisibility::normalizeAction($action);
        $key = in_array($action, ['create', 'edit'], true) ? 'writable' : 'visible';

        return array_filter($this->fields, fn ($field) => in_array($action, $field[$key], true));
    }

    /**
     * The legacy column shape the blades and the service still read (Field, Type, Null, Key, Default,
     * Extra, Comment, description, required, clean_type, input_type, max_length).
     *
     * @return list<array<string, mixed>>
     */
    public function legacyColumns(): array
    {
        return array_values(array_map(fn (array $f) => [
            'Field' => $f['name'],
            'Type' => $f['type'],
            'Null' => $f['nullable'] ? 'YES' : 'NO',
            'Key' => $f['primary'] ? 'PRI' : ($f['unique'] ? 'UNI' : ''),
            'Default' => $f['default'],
            'Extra' => $f['auto_increment'] ? 'auto_increment' : '',
            'Comment' => $f['comment'],
            'description' => ($f['help'] !== '' ? $f['help'].' ,' : '').'Default: '.($f['default'] === null ? 'NULL' : $f['default']),
            'required' => ! $f['nullable'],
            'clean_type' => $f['clean_type'],
            'input_type' => $f['input'],
            'max_length' => $f['max_length'] ?? 524288,
            'sample' => '',
        ], $this->fields));
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }

    private static function inputFor(string $cleanType): string
    {
        return match (strtolower($cleanType)) {
            'int', 'integer', 'bigint', 'smallint', 'mediumint', 'float', 'double', 'decimal', 'numeric', 'real' => 'number',
            'tinyint', 'boolean', 'bool' => 'checkbox',
            'text', 'tinytext', 'mediumtext', 'longtext', 'json', 'jsonb' => 'textarea',
            'date', 'datetime', 'timestamp', 'time' => 'date',
            default => 'text',
        };
    }
}
