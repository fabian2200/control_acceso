

<?php $__env->startSection('title', $empleado->nombre_completo); ?>
<?php $__env->startSection('crumb', 'Logs'); ?>
<?php $__env->startSection('heading', $empleado->nombre_completo); ?>

<?php $__env->startSection('actions'); ?>
    <a href="<?php echo e(route('admin.logs.index')); ?>" class="btn-ghost"><i class="fas fa-arrow-left"></i> Volver</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<section class="panel">
    <div class="panel-head log-head">
        <div>
            <h2>Log de <?php echo e($mesLabel); ?></h2>
            <p class="muted">C.C. <?php echo e($empleado->identificacion); ?><?php if($empleado->cargo_nombre): ?> · <?php echo e($empleado->cargo_nombre); ?><?php endif; ?></p>
        </div>
        <form method="GET" action="<?php echo e(route('admin.logs.show', $empleado)); ?>" class="filters log-period">
            <select name="dia" onchange="this.form.submit()">
                <option value="0" <?php if($dia === 0): echo 'selected'; endif; ?>>Todo el mes</option>
                <?php for($d = 1; $d <= $diasDelMes; $d++): ?>
                    <option value="<?php echo e($d); ?>" <?php if($dia === $d): echo 'selected'; endif; ?>>Día <?php echo e(str_pad($d, 2, '0', STR_PAD_LEFT)); ?></option>
                <?php endfor; ?>
            </select>
            <select name="mes" onchange="this.form.submit()">
                <?php $__currentLoopData = $meses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $nombre): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($num); ?>" <?php if($mes === $num): echo 'selected'; endif; ?>><?php echo e($nombre); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <select name="anio" onchange="this.form.submit()">
                <?php $__currentLoopData = $anios; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $y): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($y); ?>" <?php if($anio === $y): echo 'selected'; endif; ?>><?php echo e($y); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </form>
    </div>

    <?php if(empty($items)): ?>
        <p class="empty">Sin marcas en este periodo.</p>
    <?php else: ?>
        <ul class="log-timeline">
            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li class="log-item <?php echo e($item['alerta'] ? 'is-alerta' : ''); ?>">
                    <span class="card-icon log-item-icon">
                        <i class="fas <?php echo e(match ($item['tipo']) {
                            'entrada' => 'fa-sign-in-alt',
                            'salida' => 'fa-sign-out-alt',
                            'salida_ocasional' => 'fa-walking',
                            'regreso' => 'fa-undo',
                            'novedad' => 'fa-clipboard',
                            default => 'fa-circle',
                        }); ?>"></i>
                    </span>
                    <time><?php echo e($item['cuando']->timezone('America/Bogota')->format('d/m H:i')); ?></time>
                    <strong><?php echo e($item['titulo']); ?></strong>
                    <span><?php echo e($item['detalle']); ?></span>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    <?php endif; ?>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\control_acceso\nube\resources\views/admin/logs/show.blade.php ENDPATH**/ ?>