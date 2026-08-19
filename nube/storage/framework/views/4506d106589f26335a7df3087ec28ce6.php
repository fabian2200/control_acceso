<?php $__env->startSection('title', 'Horarios'); ?>
<?php $__env->startSection('crumb', 'Horarios'); ?>
<?php $__env->startSection('heading', 'Horarios'); ?>

<?php $__env->startSection('actions'); ?>
    <a href="<?php echo e(route('admin.horarios.crear')); ?>" class="btn-primary">Nuevo horario</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php if($horarios->isEmpty()): ?>
    <section class="panel">
        <p class="empty">No hay horarios. Crea uno con los días de la semana: jornada 1 (obligatoria si hay trabajo) y jornada 2 (opcional).</p>
    </section>
<?php else: ?>
    <div class="card-grid">
        <?php $__currentLoopData = $horarios; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $horario): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <article class="horario-card">
                <div class="horario-card-top">
                    <div>
                        <h2><?php echo e($horario->nombre); ?></h2>
                        <p><?php echo e($horario->descripcion ?: 'Sin descripción'); ?></p>
                    </div>
                    <span class="pill <?php echo e($horario->activo ? 'pill-ok' : 'pill-off'); ?>">
                        <?php echo e($horario->activo ? 'Activo' : 'Inactivo'); ?>

                    </span>
                </div>
                <ul class="dia-list">
                    <?php $__currentLoopData = $horario->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li>
                            <span><?php echo e($item->nombreDia()); ?></span>
                            <strong><?php echo e($item->resumen()); ?></strong>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
                <div class="horario-card-foot">
                    <span class="muted"><?php echo e($horario->asignaciones_count); ?> empleados</span>
                    <div class="row-actions">
                        <a href="<?php echo e(route('admin.horarios.editar', $horario)); ?>" class="btn-ghost">Editar</a>
                        <form method="POST" action="<?php echo e(route('admin.horarios.eliminar', $horario)); ?>" onsubmit="return confirm('¿Eliminar este horario?')">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="btn-danger-text">Eliminar</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\control_acceso\nube\resources\views/admin/horarios/index.blade.php ENDPATH**/ ?>