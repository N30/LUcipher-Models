<?php

namespace All1\LuModels\Tests\Feature;

use All1\LuModels\Support\Ui;
use All1\LuModels\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

/** Phase 5 §3: one blade tree, two skins. The primitives render plain HTML by default and Flux when switched on. */
class UiPrimitivesTest extends TestCase
{
    public function test_plain_is_the_default_skin(): void
    {
        $this->assertFalse(Ui::flux());

        $button = Blade::render('<x-lu_models::ui.button variant="primary">Go</x-lu_models::ui.button>');
        $this->assertStringContainsString('<button type="button"', $button);
        $this->assertStringContainsString('Go', $button);
        $this->assertStringNotContainsString('data-flux', $button);

        $link = Blade::render('<x-lu_models::ui.button href="/x">Open</x-lu_models::ui.button>');
        $this->assertStringContainsString('<a href="/x"', $link);

        $input = Blade::render('<x-lu_models::ui.input name="title" label="Title" :required="true" :maxlength="120" description="Help" value="v" />');
        $this->assertStringContainsString('<label for="lu-title"', $input);
        $this->assertMatchesRegularExpression('/<input[^>]*name="title"[^>]*required[^>]*maxlength="120"/', $input);
        $this->assertStringContainsString('Help', $input);

        $select = Blade::render('<x-lu_models::ui.select name="include" :options="[\'user\' => \'User\']" :selected="[\'user\']" :multiple="true" />');
        $this->assertStringContainsString('name="include[]"', $select);
        $this->assertMatchesRegularExpression('/<option value="user"[^>]*selected/', $select);

        $table = Blade::render('<x-lu_models::ui.table :columns="[[\'label\' => \'Title\', \'href\' => \'/s\', \'direction\' => \'asc\']]"><x-lu_models::ui.row><x-lu_models::ui.cell>A</x-lu_models::ui.cell></x-lu_models::ui.row></x-lu_models::ui.table>');
        $this->assertStringContainsString('<table', $table);
        $this->assertStringContainsString('<a href="/s"', $table);
        $this->assertStringContainsString('<td', $table);

        $menu = Blade::render('<x-lu_models::ui.menu><x-lu_models::ui.menu-item href="/d" method="DELETE" variant="danger" confirm="Sure?">Delete</x-lu_models::ui.menu-item></x-lu_models::ui.menu>');
        $this->assertStringContainsString('<details', $menu);
        $this->assertStringContainsString('name="_method" value="DELETE"', $menu);
        $this->assertStringContainsString('onsubmit="return confirm(', $menu);
    }

    public function test_flux_renders_fluxs_free_components_when_switched_on(): void
    {
        config()->set('lu::models.ui.flux', true);
        view()->share('errors', new ViewErrorBag); // no request ran, so nothing shared the error bag Flux reads
        $this->assertTrue(Ui::flux(), 'livewire/flux is installed for development');

        $button = Blade::render('<x-lu_models::ui.button variant="primary">Go</x-lu_models::ui.button>');
        $this->assertStringContainsString('data-flux-button', $button);

        $input = Blade::render('<x-lu_models::ui.input name="title" label="Title" />');
        $this->assertStringContainsString('data-flux-input', $input);

        $cell = Blade::render('<x-lu_models::ui.cell>A</x-lu_models::ui.cell>');
        $this->assertStringContainsString('data-flux-cell', $cell);
    }

    public function test_the_toasts_read_every_shape_the_controller_flashed(): void
    {
        session()->flash('toast', json_encode(['type' => 'success', 'title' => 'Saved', 'message' => 'Done']));
        $this->assertSame([['type' => 'success', 'title' => 'Saved', 'message' => 'Done']], Ui::toasts());

        session()->flash('toast', [json_encode(['type' => 'warning', 'message' => 'One']), ['type' => 'warning', 'message' => 'Two']]);
        $this->assertSame(['One', 'Two'], array_column(Ui::toasts(), 'message'));

        session()->forget('toast');
        $this->assertSame([], Ui::toasts());
        $this->assertStringContainsString('id="lu-models-toasts"', Blade::render('<x-lu_models::ui.toasts />'));
    }
}
