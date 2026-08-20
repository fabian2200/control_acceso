<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Admin'); ?> · Control de Acceso</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
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
            <a href="<?php echo e(route('admin.dashboard')); ?>" class="<?php echo e(request()->routeIs('admin.dashboard') ? 'is-on' : ''); ?>">Inicio</a>
            <a href="<?php echo e(route('admin.horarios.index')); ?>" class="<?php echo e(request()->routeIs('admin.horarios.*') ? 'is-on' : ''); ?>">Horarios</a>
            <a href="<?php echo e(route('admin.empleados.index')); ?>" class="<?php echo e(request()->routeIs('admin.empleados.*') ? 'is-on' : ''); ?>">Empleados</a>
            <a href="<?php echo e(route('admin.novedades.index')); ?>" class="<?php echo e(request()->routeIs('admin.novedades.*') ? 'is-on' : ''); ?>">Novedades</a>
        </nav>
        <form method="POST" action="<?php echo e(route('admin.logout')); ?>" class="side-out">
            <?php echo csrf_field(); ?>
            <button type="submit">Cerrar sesión</button>
            <span><?php echo e(auth('admin_acceso')->user()?->usuario); ?></span>
        </form>
    </aside>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <p class="crumb"><?php echo $__env->yieldContent('crumb', 'Admin'); ?></p>
                <h1><?php echo $__env->yieldContent('heading'); ?></h1>
            </div>
            <div class="top-actions">
                <?php echo $__env->yieldContent('actions'); ?>
            </div>
        </header>
        <?php if(session('ok')): ?>
            <div class="flash-ok"><?php echo e(session('ok')); ?></div>
        <?php endif; ?>
        <?php if($errors->any()): ?>
            <div class="flash-err"><?php echo e($errors->first()); ?></div>
        <?php endif; ?>
        <?php echo $__env->yieldContent('content'); ?>
    </main>
</div>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH D:\control_acceso\nube\resources\views/layouts/admin.blade.php ENDPATH**/ ?>