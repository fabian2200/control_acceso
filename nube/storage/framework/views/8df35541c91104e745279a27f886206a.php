<?php $__env->startSection('title', 'Inicio'); ?>
<?php $__env->startSection('crumb', 'Panel'); ?>
<?php $__env->startSection('heading', 'Resumen'); ?>

<?php $__env->startSection('content'); ?>
<div class="stat-grid">
    <article class="stat">
        <span>Horarios</span>
        <strong><?php echo e($totalHorarios); ?></strong>
    </article>
    <article class="stat">
        <span>Empleados activos</span>
        <strong><?php echo e($empleadosActivos); ?></strong>
    </article>
    <article class="stat">
        <span>Con horario</span>
        <strong><?php echo e($asignados); ?></strong>
    </article>
    <article class="stat stat-warn">
        <span>Sin horario</span>
        <strong><?php echo e($sinHorario); ?></strong>
    </article>
</div>

<section class="panel">
    <div class="panel-head">
        <h2>Horarios creados</h2>
        <a href="<?php echo e(route('admin.horarios.crear')); ?>" class="btn-primary">Nuevo horario</a>
    </div>
    <?php if($horarios->isEmpty()): ?>
        <p class="empty">Aún no hay horarios. Crea el primero para asignarlo a los empleados.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Estado</th>
                    <th>Empleados</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $horarios; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $horario): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td>
                            <strong><?php echo e($horario->nombre); ?></strong>
                            <?php if($horario->descripcion): ?>
                                <div class="muted"><?php echo e($horario->descripcion); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="pill <?php echo e($horario->activo ? 'pill-ok' : 'pill-off'); ?>">
                                <?php echo e($horario->activo ? 'Activo' : 'Inactivo'); ?>

                            </span>
                        </td>
                        <td><?php echo e($horario->asignaciones_count); ?></td>
                        <td class="td-right">
                            <a href="<?php echo e(route('admin.horarios.editar', $horario)); ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\control_acceso\nube\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>