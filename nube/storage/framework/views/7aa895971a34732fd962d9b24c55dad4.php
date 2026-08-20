

<?php $__env->startSection('title', 'Novedades'); ?>
<?php $__env->startSection('crumb', 'Novedades'); ?>
<?php $__env->startSection('heading', 'Novedades del kiosko'); ?>

<?php $__env->startSection('content'); ?>
<section class="panel">
    <form method="GET" action="<?php echo e(route('admin.novedades.index')); ?>" class="filters">
        <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Buscar por nombre o cédula">
        <select name="estado">
            <option value="todos" <?php if($estado === 'todos'): echo 'selected'; endif; ?>>Todos</option>
            <option value="pendiente" <?php if($estado === 'pendiente'): echo 'selected'; endif; ?>>Pendientes</option>
            <option value="aprobada" <?php if($estado === 'aprobada'): echo 'selected'; endif; ?>>Aprobadas</option>
            <option value="rechazada" <?php if($estado === 'rechazada'): echo 'selected'; endif; ?>>Rechazadas</option>
        </select>
        <button type="submit" class="btn-ghost">Filtrar</button>
    </form>

    <?php if($novedades->isEmpty()): ?>
        <p class="empty">No hay novedades con ese filtro.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Empleado</th>
                        <th>Jornada</th>
                        <th>Horario</th>
                        <th>Motivo</th>
                        <th>Autoriza</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $novedades; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $novedad): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $inicio = $novedad->hora_inicio_jornada ? substr((string) $novedad->hora_inicio_jornada, 0, 5) : '—';
                            $fin = $novedad->hora_fin_jornada ? substr((string) $novedad->hora_fin_jornada, 0, 5) : '—';
                            $pill = match ($novedad->aprobada) {
                                1 => 'pill-ok',
                                0 => 'pill-off',
                                default => 'pill-warn',
                            };
                        ?>
                        <tr>
                            <td><?php echo e(optional($novedad->fecha)?->format('d/m/Y')); ?></td>
                            <td>
                                <strong><?php echo e($novedad->empleado?->nombre_completo ?? '—'); ?></strong>
                                <div class="muted"><?php echo e($novedad->empleado?->identificacion); ?></div>
                            </td>
                            <td>Jornada <?php echo e($novedad->jornada); ?></td>
                            <td><?php echo e($inicio); ?> – <?php echo e($fin); ?></td>
                            <td><?php echo e($novedad->motivo); ?></td>
                            <td><?php echo e($novedad->quien_autoriza ?: '—'); ?></td>
                            <td>
                                <span class="pill <?php echo e($pill); ?>"><?php echo e($novedad->estadoEtiqueta()); ?></span>
                            </td>
                            <td class="td-right">
                                <?php if($novedad->aprobada === null): ?>
                                    <form method="POST" action="<?php echo e(route('admin.novedades.aprobar', $novedad)); ?>" style="display:inline">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="btn-ghost btn-sm">Aprobar</button>
                                    </form>
                                    <form method="POST" action="<?php echo e(route('admin.novedades.rechazar', $novedad)); ?>" style="display:inline">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="btn-ghost btn-sm">Rechazar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\control_acceso\nube\resources\views/admin/novedades/index.blade.php ENDPATH**/ ?>