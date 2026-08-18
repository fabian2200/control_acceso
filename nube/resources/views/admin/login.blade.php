<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingreso · Admin acceso</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="login-body">
<form method="POST" action="{{ route('admin.login.guardar') }}" class="login-card">
    @csrf
    <div class="brand login-brand">
        <span class="brand-dot"></span>
        <div>
            <strong>Control de acceso</strong>
            <small>Gestión de horarios</small>
        </div>
    </div>
    <h1>Ingresar al panel</h1>
    <p class="login-lead">Usa el usuario de <code>admin_acceso</code>.</p>
    @if ($errors->any())
        <div class="flash-err">{{ $errors->first() }}</div>
    @endif
    <label>
        Usuario
        <input type="text" name="usuario" value="{{ old('usuario') }}" autocomplete="username" autofocus required>
    </label>
    <label>
        Contraseña
        <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="btn-primary">Entrar</button>
</form>
</body>
</html>
