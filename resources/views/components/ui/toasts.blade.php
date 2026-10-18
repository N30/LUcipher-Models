{{--
    The toasts for this page: what the controller flashed after a write, plus anything a Livewire
    component dispatches as a `lu-models:toast` browser event ({type, title, message}). Plain in both
    skins: it needs no Flux and no host JavaScript.
--}}
@php $toasts = \All1\LuModels\Support\Ui::toasts(); @endphp
<div id="lu-models-toasts" class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 flex-col gap-2" aria-live="polite">
    @foreach ($toasts as $toast)
        <div class="lu-toast pointer-events-auto rounded-md border px-3 py-2 text-sm shadow {{ $toast['type'] === 'success' ? 'border-green-200 bg-green-50 text-green-900' : ($toast['type'] === 'warning' || $toast['type'] === 'error' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-zinc-200 bg-white text-zinc-800') }}" role="status">
            @if ($toast['title'] !== '')<strong class="block">{{ $toast['title'] }}</strong>@endif
            <span>{{ $toast['message'] }}</span>
        </div>
    @endforeach
</div>
<script>
    (function () {
        var box = document.getElementById('lu-models-toasts');
        if (!box || box.dataset.ready) return;
        box.dataset.ready = '1';
        var fade = function (el) { setTimeout(function () { el.remove(); }, 6000); };
        Array.prototype.forEach.call(box.children, fade);
        window.addEventListener('lu-models:toast', function (event) {
            var d = (event.detail && event.detail[0]) || event.detail || {};
            var el = document.createElement('div');
            el.className = 'lu-toast pointer-events-auto rounded-md border px-3 py-2 text-sm shadow ' + (d.type === 'success' ? 'border-green-200 bg-green-50 text-green-900' : (d.type === 'warning' || d.type === 'error' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-zinc-200 bg-white text-zinc-800'));
            el.setAttribute('role', 'status');
            if (d.title) { var t = document.createElement('strong'); t.className = 'block'; t.textContent = d.title; el.appendChild(t); }
            var m = document.createElement('span'); m.textContent = d.message || ''; el.appendChild(m);
            box.appendChild(el);
            fade(el);
        });
    })();
</script>
