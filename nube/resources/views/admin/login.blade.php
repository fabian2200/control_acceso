<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingreso · Admin acceso</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="login-body">
<form method="POST" action="{{ route('admin.login.guardar') }}" class="login-card">
    @csrf
    <div class="brand login-brand">
        <span class="brand-dot"><i class="fas fa-id-badge"></i></span>
        <div>
            <strong>Control de acceso</strong>
            <small>Gestión de horarios</small>
        </div>
    </div>
    <h1>Ingresar al panel</h1>
    <p class="login-lead">Usa el usuario de <code>admin_acceso</code>.</p>
    @if ($errors->any())
        <div class="flash-err"><i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}</div>
    @endif
    <label>
        Usuario
        <input type="text" name="usuario" value="{{ old('usuario') }}" autocomplete="username" autofocus required>
    </label>
    <label>
        Contraseña
        <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="btn-primary"><i class="fas fa-sign-in-alt"></i> Entrar</button>
</form>
</body>
</html>
