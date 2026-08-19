<?php $__env->startSection('title', $horario->exists ? 'Editar horario' : 'Nuevo horario'); ?>
<?php $__env->startSection('crumb', 'Horarios'); ?>
<?php $__env->startSection('heading', $horario->exists ? 'Editar horario' : 'Nuevo horario'); ?>

<?php $__env->startSection('actions'); ?>
    <a href="<?php echo e(route('admin.horarios.index')); ?>" class="btn-ghost">Volver</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<form method="POST" action="<?php echo e($horario->exists ? route('admin.horarios.actualizar', $horario) : route('admin.horarios.guardar')); ?>" class="panel">
    <?php echo csrf_field(); ?>
    <?php if($horario->exists): ?>
        <?php echo method_field('PUT'); ?>
    <?php endif; ?>

    <div class="form-grid">
        <label>
            Nombre
            <input type="text" name="nombre" value="<?php echo e(old('nombre', $horario->nombre)); ?>" required maxlength="120">
        </label>
        <label>
            Descripción
            <input type="text" name="descripcion" value="<?php echo e(old('descripcion', $horario->descripcion)); ?>" maxlength="255" placeholder="Opcional">
        </label>
        <label class="check check-box">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" <?php if(old('activo', $horario->activo)): echo 'checked'; endif; ?>>
            Horario activo
        </label>
    </div>

    <div class="days-head">
        <h2>Días de la semana</h2>
        <button type="button" class="btn-ghost" id="copiarLunes">Copiar lunes a lun–vie</button>
    </div>
    <p class="muted days-hint">Jornada 1 es el primer bloque del día (p. ej. 08:00–15:00 o 08:00–12:00). Jornada 2 es opcional, para un segundo bloque. Cada marca tiene gabela en minutos. Vacío = descanso.</p>

    <div class="table-wrap">
        <table class="table days-table">
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Entrada jornada 1</th>
                    <th>Salida jornada 1</th>
                    <th>Entrada jornada 2</th>
                    <th>Salida jornada 2</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dia => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $oldItem = old('items.'.$dia, $item); ?>
                    <tr>
                        <td><strong><?php echo e($item['nombre']); ?></strong></td>
                        <?php $__currentLoopData = ['entrada_jornada_1', 'salida_jornada_1', 'entrada_jornada_2', 'salida_jornada_2']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <td>
                                <div class="slot">
                                    <input type="time" name="items[<?php echo e($dia); ?>][<?php echo e($campo); ?>]" value="<?php echo e($oldItem[$campo] ?? ''); ?>" data-dia="<?php echo e($dia); ?>" data-campo="<?php echo e($campo); ?>">
                                    <label class="gabela">
                                        <span>Gabela</span>
                                        <input type="number" min="0" max="180" name="items[<?php echo e($dia); ?>][gabela_<?php echo e($campo); ?>]" value="<?php echo e($oldItem['gabela_'.$campo] ?? ''); ?>" placeholder="0" data-dia="<?php echo e($dia); ?>" data-campo="gabela_<?php echo e($campo); ?>">
                                        <em>min</em>
                                    </label>
                                </div>
                            </td>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-primary"><?php echo e($horario->exists ? 'Guardar cambios' : 'Crear horario'); ?></button>
        <a href="<?php echo e(route('admin.horarios.index')); ?>" class="btn-ghost">Cancelar</a>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.getElementById('copiarLunes')?.addEventListener('click', function () {
        ['entrada_jornada_1', 'salida_jornada_1', 'entrada_jornada_2', 'salida_jornada_2',
         'gabela_entrada_jornada_1', 'gabela_salida_jornada_1', 'gabela_entrada_jornada_2', 'gabela_salida_jornada_2'].forEach(function (campo) {
            const origen = document.querySelector('[data-dia="1"][data-campo="' + campo + '"]');
            if (!origen) return;
            for (let dia = 2; dia <= 5; dia++) {
                const dest = document.querySelector('[data-dia="' + dia + '"][data-campo="' + campo + '"]');
                if (dest) dest.value = origen.value;
            }
        });
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\control_acceso\nube\resources\views/admin/horarios/form.blade.php ENDPATH**/ ?>