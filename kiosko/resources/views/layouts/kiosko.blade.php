<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Control de Acceso') · Recepción</title>
    <link rel="stylesheet" href="{{ asset('css/kiosko.css') }}">
    @stack('head')
</head>
<body>
<div class="kiosk-page">
    <div class="kiosk-frame">
        <div class="kiosk-screen">
            <div class="kiosk-body">
                <div class="screen is-on @yield('screen_class')">
                    @yield('content')
                </div>
            </div>
            <div class="kiosk-bar">
                <div class="net {{ ($syncUi['en_linea'] ?? false) ? '' : 'is-off' }}">
                    <span class="net-dot"></span>
                    {{ $syncUi['etiqueta_red'] ?? 'Kiosko local' }}
                </div>
                <div class="bar-meta">
                    <span data-sync-label>{{ $syncUi['etiqueta_sync'] ?? 'Local' }}</span>
                    <span class="bar-sep"></span>
                    <span>Terminal {{ config('acceso.terminal_codigo') }} · v2.4</span>
                </div>
            </div>
        </div>
    </div>
</div>
@stack('scripts')
<script>
    (function () {
        var url = @json(route('kiosko.sync'));
        var token = document.querySelector('meta[name="csrf-token"]');
        if (!token) return;

        function sync() {
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token.getAttribute('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (res) { return res.json(); }).then(function (data) {
                if (!data || !data.ui) return;
                var net = document.querySelector('.net');
                var label = document.querySelector('[data-sync-label]');
                if (net) {
                    net.classList.toggle('is-off', !data.ui.en_linea);
                    var text = net.childNodes[net.childNodes.length - 1];
                    if (text) text.textContent = ' ' + data.ui.etiqueta_red;
                }
                if (label) label.textContent = data.ui.etiqueta_sync;
            }).catch(function () {});
        }

        setTimeout(sync, 4000);
        setInterval(sync, 60000);
    })();
</script>
</body>
</html>
