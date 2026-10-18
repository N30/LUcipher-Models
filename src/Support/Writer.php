<?php

namespace All1\LuModels\Support;

use All1\LuModels\Services\LuModels;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The write pipeline every transport shares: inject what the package owns (user_id, ip_address,
 * user_agent, the slug, the session's `{model}_{field}` prefills), validate with the model's rules and
 * refuse every key the model does not allow written, then run the operation (or, on a dry run, say
 * what it would do) and hand back one Result. The API controller, the web and rest controllers and the
 * Livewire form all call this, so they cannot disagree about what a write accepts.
 */
final class Writer
{
    public const CREATED = 'We have received and saved your information successfully.';

    public const UPDATED = 'Record updated';

    public const DELETED = 'Record deleted';

    /** @param  array<string, mixed>  $data */
    public static function create(Model $model, array $data, ?string $transport, bool $dryRun = false): Result
    {
        $client_keys = array_keys($data);
        $data = self::inject($model, $data);

        $validator = self::validator($model, $data, 'create', $client_keys);
        if ($validator->fails()) {
            return Result::invalid($validator);
        }

        $service = new LuModels;
        if ($dryRun) {
            return LuModels::wouldDo('create', $service, $model, $data, $transport);
        }

        $record = self::recordOf(LuModels::fromData('create', $service, $model, $data, $transport));

        return Result::ok(self::CREATED, $record, $record, $record ? encrypt($record->getKey()) : null);
    }

    /** @param  array<string, mixed>  $data */
    public static function update(Model $record, array $data, ?string $transport, bool $dryRun = false): Result
    {
        $validator = self::validator($record, $data, 'update', array_keys($data));
        if ($validator->fails()) {
            return Result::invalid($validator);
        }

        $service = new LuModels;
        if ($dryRun) {
            return LuModels::wouldDo('update', $service, $record, $data, $transport);
        }

        $written = self::recordOf(LuModels::fromData('update', $service, $record, $data, $transport), $record);

        return Result::ok(self::UPDATED, $written, $written, encrypt($written->getKey()));
    }

    public static function delete(Model $record, ?string $transport, bool $dryRun = false): Result
    {
        $service = new LuModels;
        if ($dryRun) {
            return LuModels::wouldDo('delete', $service, $record, [], $transport);
        }

        $deleted = self::recordOf(LuModels::fromData('delete', $service, $record, [], $transport), $record);

        return Result::ok(self::DELETED, $deleted, $deleted);
    }

    /**
     * The validator for a write: the model's rules for the action plus a rejection of every client key
     * the model does not allow written (not fillable, guarded, or kept from this action by $lu_fields).
     * Rejecting beats silently dropping: the caller learns the contract.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $client_keys
     */
    public static function validator(Model $model, array $data, string $action, array $client_keys): ValidatorContract
    {
        $rules = (array) ($model->{$action === 'create' ? 'store_validation_rules' : 'update_validation_rules'} ?? []);
        $rejected = FieldVisibility::for($model)->rejectedWrites($client_keys, $action);

        return Validator::make($data, $rules)->after(function ($validator) use ($rejected) {
            foreach ($rejected as $key) {
                $validator->errors()->add($key, 'The '.$key.' field cannot be written.');
            }
        });
    }

    /**
     * The record a write produced. The package operations return the service (its ->model is the
     * record); an app's own Action or Verb override may return the model itself. Anything else falls
     * back to the record the caller already had.
     */
    public static function recordOf(mixed $result, ?Model $fallback = null): ?Model
    {
        if ($result instanceof Model) {
            return $result;
        }
        if (is_object($result) && isset($result->model) && $result->model instanceof Model) {
            return $result->model;
        }

        return $fallback;
    }

    /**
     * What the package writes itself on create, whatever the client sent: the signed-in user, the
     * request's address and agent where the table has the columns, the session's `{model}_{field}`
     * prefills for fields the client left out (3.0.x's "opinionated type hinting"), and the slug.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function inject(Model $model, array $data): array
    {
        $descriptor = Descriptor::for($model);

        if ($descriptor->field('ip_address') !== null) {
            $data['ip_address'] = request()->ip();
        }
        if ($descriptor->field('user_id') !== null) {
            $data['user_id'] = auth()->id();
        }
        if ($descriptor->field('user_agent') !== null) {
            $data['user_agent'] = request()->header('User-Agent');
        }

        $prefix = strtolower(class_basename($model)).'_';
        foreach (session()->all() as $key => $value) {
            if (is_string($key) && Str::startsWith($key, $prefix)) {
                $field = Str::after($key, $prefix);
                if (! array_key_exists($field, $data) && $descriptor->field($field) !== null) {
                    $data[$field] = $value;
                }
            }
        }

        if (method_exists($model, 'getSlugOptions')) {
            $from = $model->getSlugOptions()->generateSlugFrom[0];
            $data['slug'] = Str::slug((string) ($data[$from] ?? request()->input($from)));
        }

        return $data;
    }
}
