<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Admin'); ?> · Control de Acceso</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
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
            <a href="<?php echo e(route('admin.dashboard')); ?>" class="<?php echo e(request()->routeIs('admin.dashboard') ? 'is-on' : ''); ?>"><i class="fas fa-home"></i> Inicio</a>
            <a href="<?php echo e(route('admin.horarios.index')); ?>" class="<?php echo e(request()->routeIs('admin.horarios.*') ? 'is-on' : ''); ?>"><i class="fas fa-clock"></i> Horarios</a>
            <a href="<?php echo e(route('admin.empleados.index')); ?>" class="<?php echo e(request()->routeIs('admin.empleados.*') ? 'is-on' : ''); ?>"><i class="fas fa-users"></i> Empleados</a>
            <a href="<?php echo e(route('admin.novedades.index')); ?>" class="<?php echo e(request()->routeIs('admin.novedades.*') ? 'is-on' : ''); ?>"><i class="fas fa-clipboard-list"></i> Novedades</a>
            <a href="<?php echo e(route('admin.logs.index')); ?>" class="<?php echo e(request()->routeIs('admin.logs.*') ? 'is-on' : ''); ?>"><i class="fas fa-list-alt"></i> Logs</a>
            <a href="<?php echo e(route('admin.llegadas-tarde.index')); ?>" class="<?php echo e(request()->routeIs('admin.llegadas-tarde.*') ? 'is-on' : ''); ?>"><i class="fas fa-exclamation-triangle"></i> Llegadas tarde</a>
        </nav>
        <form method="POST" action="<?php echo e(route('admin.logout')); ?>" class="side-out">
            <?php echo csrf_field(); ?>
            <button type="submit"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</button>
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
            <div class="flash-ok"><i class="fas fa-check-circle"></i> <?php echo e(session('ok')); ?></div>
        <?php endif; ?>
        <?php if($errors->any()): ?>
            <div class="flash-err"><i class="fas fa-exclamation-circle"></i> <?php echo e($errors->first()); ?></div>
        <?php endif; ?>
        <?php echo $__env->yieldContent('content'); ?>
    </main>
</div>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH D:\control_acceso\nube\resources\views/layouts/admin.blade.php ENDPATH**/ ?>