

<?php $__env->startSection('title', 'Logs'); ?>
<?php $__env->startSection('crumb', 'Logs'); ?>
<?php $__env->startSection('heading', 'Logs de empleados'); ?>

<?php $__env->startSection('content'); ?>
<section class="panel">
    <form method="GET" action="<?php echo e(route('admin.logs.index')); ?>" class="filters">
        <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Buscar por nombre o cédula">
        <button type="submit" class="btn-ghost"><i class="fas fa-filter"></i> Filtrar</button>
    </form>

    <?php if($empleados->isEmpty()): ?>
        <p class="empty">No hay empleados con ese filtro.</p>
    <?php else: ?>
        <div class="log-emp-list">
            <?php $__currentLoopData = $empleados; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $empleado): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a class="log-emp-row" href="<?php echo e(route('admin.logs.show', $empleado)); ?>">
                    <div class="log-emp-main">
                        <div class="card-icon"><i class="fas fa-user"></i></div>
                        <div>
                            <strong><?php echo e($empleado->nombre_completo); ?></strong>
                            <div class="muted">C.C. <?php echo e($empleado->identificacion); ?><?php if($empleado->cargo_nombre): ?> · <?php echo e($empleado->cargo_nombre); ?><?php endif; ?></div>
                        </div>
                    </div>
                    <span class="log-emp-go"><i class="fas fa-chevron-right"></i> Ver log</span>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\control_acceso\nube\resources\views/admin/logs/index.blade.php ENDPATH**/ ?>