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
                <div class="net"><span class="net-dot"></span> En línea</div>
                <div class="bar-meta">
                    <span>Todo sincronizado</span>
                    <span class="bar-sep"></span>
                    <span>Terminal {{ config('acceso.terminal_codigo') }} · v2.4</span>
                </div>
            </div>
        </div>
    </div>
</div>
@stack('scripts')
</body>
</html>
