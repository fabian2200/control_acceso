<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Ingreso · Admin acceso</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
</head>
<body class="login-body">
<form method="POST" action="<?php echo e(route('admin.login.guardar')); ?>" class="login-card">
    <?php echo csrf_field(); ?>
    <div class="brand login-brand">
        <span class="brand-dot"></span>
        <div>
            <strong>Control de acceso</strong>
            <small>Gestión de horarios</small>
        </div>
    </div>
    <h1>Ingresar al panel</h1>
    <p class="login-lead">Usa el usuario de <code>admin_acceso</code>.</p>
    <?php if($errors->any()): ?>
        <div class="flash-err"><?php echo e($errors->first()); ?></div>
    <?php endif; ?>
    <label>
        Usuario
        <input type="text" name="usuario" value="<?php echo e(old('usuario')); ?>" autocomplete="username" autofocus required>
    </label>
    <label>
        Contraseña
        <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="btn-primary">Entrar</button>
</form>
</body>
</html>
<?php /**PATH D:\control_acceso\nube\resources\views/admin/login.blade.php ENDPATH**/ ?>