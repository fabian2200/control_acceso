<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · Control de Acceso</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-side">
        <div class="brand">
            <span class="brand-dot"><i class="fas fa-id-badge"></i></span>
            <div>
                <strong>Control de acceso</strong>
                <small>Panel de administración</small>
            </div>
        </div>
        <nav class="side-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-on' : '' }}"><i class="fas fa-home"></i> Inicio</a>
            <a href="{{ route('admin.horarios.index') }}" class="{{ request()->routeIs('admin.horarios.*') ? 'is-on' : '' }}"><i class="fas fa-clock"></i> Horarios</a>
            <a href="{{ route('admin.empleados.index') }}" class="{{ request()->routeIs('admin.empleados.*') ? 'is-on' : '' }}"><i class="fas fa-users"></i> Empleados</a>
            <a href="{{ route('admin.novedades.index') }}" class="{{ request()->routeIs('admin.novedades.*') ? 'is-on' : '' }}"><i class="fas fa-clipboard-list"></i> Novedades</a>
            <a href="{{ route('admin.logs.index') }}" class="{{ request()->routeIs('admin.logs.*') ? 'is-on' : '' }}"><i class="fas fa-list-alt"></i> Logs</a>
            <a href="{{ route('admin.llegadas-tarde.index') }}" class="{{ request()->routeIs('admin.llegadas-tarde.*') ? 'is-on' : '' }}"><i class="fas fa-user-clock"></i> Asistencia horaria</a>
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}" class="side-out">
            @csrf
            <button type="submit"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</button>
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
            <div class="flash-ok"><i class="fas fa-check-circle"></i> {{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash-err"><i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
