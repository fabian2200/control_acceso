<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · Control de Acceso</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-side">
        <div class="brand">
            <span class="brand-dot"></span>
            <div>
                <strong>Control de acceso</strong>
                <small>Panel de administración</small>
            </div>
        </div>
        <nav class="side-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-on' : '' }}">Inicio</a>
            <a href="{{ route('admin.horarios.index') }}" class="{{ request()->routeIs('admin.horarios.*') ? 'is-on' : '' }}">Horarios</a>
            <a href="{{ route('admin.empleados.index') }}" class="{{ request()->routeIs('admin.empleados.*') ? 'is-on' : '' }}">Empleados</a>
            <a href="{{ route('admin.novedades.index') }}" class="{{ request()->routeIs('admin.novedades.*') ? 'is-on' : '' }}">Novedades</a>
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}" class="side-out">
            @csrf
            <button type="submit">Cerrar sesión</button>
            <span>{{ auth('admin_acceso')->user()?->usuario }}</span>
        </form>
    </aside>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <p class="crumb">@yield('crumb', 'Admin')</p>
                <h1>@yield('heading')</h1>
            </div>
            <div class="top-actions">
                @yield('actions')
            </div>
        </header>
        @if (session('ok'))
            <div class="flash-ok">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash-err">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
