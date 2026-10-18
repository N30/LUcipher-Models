<?php

namespace All1\LuModels\Support;

use Flux\Flux;
use Illuminate\Support\Str;

/**
 * The UI seam. The shipped blades are written against the `lu_models::ui.*` primitives, which render
 * plain HTML with Tailwind classes by default and Flux's free components when `lu::models.ui.flux` is
 * on and livewire/flux is installed. One blade tree, two skins; publish the views for anything more.
 */
final class Ui
{
    public static function flux(): bool
    {
        return (bool) config('lu::models.ui.flux') && class_exists(Flux::class);
    }

    /** The host layout the pages extend: `lu::models.ui.layout`, else the older `lu::models.layout`. */
    public static function layout(): string
    {
        return (string) (config('lu::models.ui.layout') ?: config('lu::models.layout', 'components.layouts.app'));
    }

    public static function label(string $field): string
    {
        return __(config('lu::models.lang_prefix').Str::headline(str_replace('.', ' ', $field)));
    }

    /**
     * The toasts flashed for this request, whatever shape the controller flashed them in: one JSON
     * string, a list of JSON strings, or arrays.
     *
     * @return list<array{type: string, title: string, message: string}>
     */
    public static function toasts(): array
    {
        $raw = session('toast');
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        $items = is_array($raw) && ! isset($raw['message']) ? $raw : [$raw];
        $toasts = [];
        foreach ($items as $item) {
            $decoded = is_string($item) ? json_decode($item, true) : $item;
            if (is_array($decoded) && isset($decoded['message'])) {
                $toasts[] = [
                    'type' => (string) ($decoded['type'] ?? 'info'),
                    'title' => (string) ($decoded['title'] ?? ''),
                    'message' => (string) $decoded['message'],
                ];
            }
        }

        return $toasts;
    }
}
