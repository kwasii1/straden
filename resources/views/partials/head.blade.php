<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Straden') : config('app.name', 'Straden') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

{{-- Runtime (not build-time) websocket settings so one Docker image works on any host. --}}
<script>
    window.StradenConfig = {{ Js::from([
        'reverb' => [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('reverb.client.host'),
            'port' => config('reverb.client.port'),
            'scheme' => config('reverb.client.scheme'),
        ],
    ]) }};
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
<script>try { localStorage.setItem('flux.appearance', 'light'); } catch (e) {}</script>
@fluxAppearance
