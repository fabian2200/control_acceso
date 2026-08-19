<?php $__env->startSection('title', 'Empleados'); ?>
<?php $__env->startSection('crumb', 'Empleados'); ?>
<?php $__env->startSection('heading', 'Asignar horarios'); ?>

<?php $__env->startSection('content'); ?>
<section class="panel">
    <form method="GET" action="<?php echo e(route('admin.empleados.index')); ?>" class="filters">
        <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Buscar por nombre o cédula">
        <select name="filtro">
            <option value="todos" <?php if($filtro === 'todos'): echo 'selected'; endif; ?>>Todos</option>
            <option value="asignados" <?php if($filtro === 'asignados'): echo 'selected'; endif; ?>>Con horario</option>
            <option value="sin_horario" <?php if($filtro === 'sin_horario'): echo 'selected'; endif; ?>>Sin horario</option>
        </select>
        <button type="submit" class="btn-ghost">Filtrar</button>
    </form>

    <?php if($empleados->isEmpty()): ?>
        <p class="empty">No hay empleados con ese filtro.</p>
    <?php else: ?>
        <form method="POST" action="<?php echo e(route('admin.empleados.asignar-lote')); ?>" id="formLote">
            <?php echo csrf_field(); ?>
            <div class="lote-bar">
                <label>
                    Asignar a los seleccionados
                    <select name="horario_id">
                        <option value="">Quitar horario</option>
                        <?php $__currentLoopData = $horarios; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $horario): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($horario->id); ?>"><?php echo e($horario->nombre); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </label>
                <button type="submit" class="btn-primary">Aplicar</button>
            </div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="th-check"><input type="checkbox" id="checkAll"></th>
                        <th>Empleado</th>
                        <th>Cédula</th>
                        <th>Cargo</th>
                        <th>Horario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $empleados; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $empleado): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td>
                                <input type="checkbox" form="formLote" name="empleado_ids[]" value="<?php echo e($empleado->id); ?>" class="row-check">
                            </td>
                            <td><strong><?php echo e($empleado->nombre_completo); ?></strong></td>
                            <td><?php echo e($empleado->identificacion); ?></td>
                            <td><?php echo e($empleado->cargo_nombre); ?></td>
                            <td>
                                <form method="POST" action="<?php echo e(route('admin.empleados.asignar', $empleado)); ?>" class="inline-assign">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PUT'); ?>
                                    <select name="horario_id">
                                        <option value="">Sin horario</option>
                                        <?php $__currentLoopData = $horarios; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $horario): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($horario->id); ?>" <?php if($empleado->asignacionHorario?->horario_id == $horario->id): echo 'selected'; endif; ?>>
                                                <?php echo e($horario->nombre); ?>

                                            </option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                    <button type="submit" class="btn-ghost btn-sm">Guardar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    const all = document.getElementById('checkAll');
    all?.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(function (el) {
            el.checked = all.checked;
        });
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\control_acceso\nube\resources\views/admin/empleados/index.blade.php ENDPATH**/ ?>