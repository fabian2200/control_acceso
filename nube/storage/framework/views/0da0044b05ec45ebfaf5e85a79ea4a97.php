

<?php $__env->startSection('title', 'Llegadas tarde'); ?>
<?php $__env->startSection('crumb', 'Informe'); ?>
<?php $__env->startSection('heading', 'Informe de llegadas tarde'); ?>

<?php $__env->startSection('actions'); ?>
    <a href="<?php echo e(route('admin.llegadas-tarde.pdf', request()->query())); ?>" class="btn-primary"><i class="fas fa-file-pdf"></i> Exportar PDF</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="kpi-grid kpi-grid-6">
    <article class="kpi">
        <div class="card-icon"><i class="fas fa-clock"></i></div>
        <div class="kpi-body">
            <span>Llegadas tarde</span>
            <strong><?php echo e($kpis['total']); ?></strong>
        </div>
    </article>
    <article class="kpi">
        <div class="card-icon"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-body">
            <span>Justificadas</span>
            <strong><?php echo e($kpis['justificadas']); ?></strong>
        </div>
    </article>
    <article class="kpi kpi-alert">
        <div class="card-icon"><i class="fas fa-times-circle"></i></div>
        <div class="kpi-body">
            <span>Sin justificar</span>
            <strong><?php echo e($kpis['sin']); ?></strong>
        </div>
    </article>
    <article class="kpi kpi-incompleta">
        <div class="card-icon"><i class="fas fa-minus-circle"></i></div>
        <div class="kpi-body">
            <span>Marc. incompleta</span>
            <strong><?php echo e($kpis['incompletas']); ?></strong>
        </div>
    </article>
    <article class="kpi">
        <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="kpi-body">
            <span>Tiempo acumulado</span>
            <strong><?php echo e(\App\Services\LlegadaTardeService::minutosLabel($kpis['minutos'])); ?></strong>
        </div>
    </article>
    <article class="kpi">
        <div class="card-icon"><i class="fas fa-users"></i></div>
        <div class="kpi-body">
            <span>Empleados</span>
            <strong><?php echo e($kpis['empleados']); ?></strong>
        </div>
    </article>
</div>

<section class="panel tarde-panel">
    <div class="panel-head tarde-head">
        <div>
            <h2>Detalle de llegadas</h2>
            <p class="tarde-legend">
                Franja <em class="lg-novedad">verde</em>: novedad.
                <em class="lg-permiso">ámbar</em>: permiso.
                <em class="lg-sin">granate</em>: tarde sin respaldo.
                <em class="lg-incompleta">azul</em>: no marcó la entrada.
            </p>
        </div>
    </div>

    <form method="GET" action="<?php echo e(route('admin.llegadas-tarde.index')); ?>" class="tarde-filters" id="formTarde">
        <select name="empleado_id">
            <option value="">Todos los empleados</option>
            <?php $__currentLoopData = $empleados; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($emp->id); ?>" <?php if($empleado_id === $emp->id): echo 'selected'; endif; ?>><?php echo e($emp->nombre_completo); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <select name="mes">
            <?php $__currentLoopData = $meses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $nombre): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($num); ?>" <?php if($mes === $num): echo 'selected'; endif; ?>><?php echo e($nombre); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <select name="anio">
            <?php $__currentLoopData = $anios; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $y): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($y); ?>" <?php if($anio === $y): echo 'selected'; endif; ?>><?php echo e($y); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <input type="hidden" name="respaldo" value="<?php echo e($respaldo); ?>">
    </form>

    <div class="tarde-chips">
        <?php
            $base = request()->except('respaldo');
        ?>
        <a class="chip <?php echo e($respaldo === 'sin' ? 'is-on chip-sin' : ''); ?>" href="<?php echo e(route('admin.llegadas-tarde.index', array_merge($base, ['respaldo' => $respaldo === 'sin' ? 'todos' : 'sin']))); ?>"><i class="fas fa-times-circle"></i> Sin justificar</a>
        <a class="chip <?php echo e($respaldo === 'novedad' ? 'is-on chip-novedad' : ''); ?>" href="<?php echo e(route('admin.llegadas-tarde.index', array_merge($base, ['respaldo' => $respaldo === 'novedad' ? 'todos' : 'novedad']))); ?>"><i class="fas fa-clipboard"></i> Con novedad</a>
        <a class="chip <?php echo e($respaldo === 'permiso' ? 'is-on chip-permiso' : ''); ?>" href="<?php echo e(route('admin.llegadas-tarde.index', array_merge($base, ['respaldo' => $respaldo === 'permiso' ? 'todos' : 'permiso']))); ?>"><i class="fas fa-id-card"></i> Con permiso</a>
        <a class="chip <?php echo e($respaldo === 'incompleta' ? 'is-on chip-incompleta' : ''); ?>" href="<?php echo e(route('admin.llegadas-tarde.index', array_merge($base, ['respaldo' => $respaldo === 'incompleta' ? 'todos' : 'incompleta']))); ?>"><i class="fas fa-minus-circle"></i> Marcación incompleta</a>
        <button type="button" class="chip" id="btnExpandir"><i class="fas fa-expand-alt"></i> Desplegar todo</button>
    </div>

    <?php if(empty($filas)): ?>
        <p class="empty">No hay llegadas tarde ni marcaciones incompletas con ese filtro.</p>
    <?php else: ?>
        <div class="tarde-table-head">
            <span>Empleado</span>
            <span>Día</span>
            <span>Entrada</span>
            <span>Marcó</span>
            <span>Tarde</span>
            <span>Respaldo</span>
        </div>
        <div class="tarde-list" id="listaTarde">
            <?php $__currentLoopData = $filas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fila): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <details class="tarde-row is-<?php echo e($fila['respaldo']); ?>">
                    <summary>
                        <span class="tarde-emp">
                            <span class="card-icon tarde-row-icon">
                                <i class="fas <?php echo e(match ($fila['respaldo']) {
                                    'novedad' => 'fa-clipboard',
                                    'permiso' => 'fa-id-card',
                                    'incompleta' => 'fa-minus-circle',
                                    default => 'fa-exclamation-circle',
                                }); ?>"></i>
                            </span>
                            <span>
                                <strong><?php echo e($fila['nombre']); ?></strong>
                                <small><?php echo e($fila['identificacion']); ?></small>
                            </span>
                        </span>
                        <span><?php echo e($fila['dia_label']); ?></span>
                        <span><?php echo e($fila['entrada']); ?></span>
                        <span><?php echo e($fila['marco']); ?></span>
                        <span class="<?php echo e(($fila['tipo'] ?? '') === 'incompleta' ? 'tarde-incomp' : 'tarde-mins'); ?>"><?php echo e($fila['tarde_label']); ?></span>
                        <span class="pill pill-<?php echo e($fila['respaldo']); ?>"><?php echo e($fila['respaldo_label']); ?></span>
                    </summary>
                    <div class="tarde-detail">
                        <p class="tarde-detail-title"><?php echo e($fila['titulo_detalle']); ?> — <?php echo e($fila['mensaje']); ?></p>
                        <dl class="tarde-dl">
                            <div>
                                <dt>Debía entrar</dt>
                                <dd><?php echo e($fila['entrada']); ?></dd>
                            </div>
                            <div>
                                <dt>Marcó</dt>
                                <dd><?php echo e($fila['marco']); ?></dd>
                            </div>
                            <div>
                                <dt>Retraso</dt>
                                <dd><?php echo e($fila['tarde_label']); ?></dd>
                            </div>
                            <div>
                                <dt>Cargo</dt>
                                <dd><?php echo e($fila['cargo']); ?></dd>
                            </div>
                        </dl>
                        <p class="muted tarde-pie"><?php echo e($fila['pie']); ?></p>
                    </div>
                </details>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>
</section>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    const btn = document.getElementById('btnExpandir');
    const lista = document.getElementById('listaTarde');
    btn?.addEventListener('click', function () {
        const rows = lista.querySelectorAll('details');
        const abrir = [...rows].some((el) => !el.open);
        rows.forEach((el) => { el.open = abrir; });
        btn.innerHTML = abrir
            ? '<i class="fas fa-compress-alt"></i> Contraer todo'
            : '<i class="fas fa-expand-alt"></i> Desplegar todo';
    });
    document.getElementById('formTarde')?.querySelectorAll('select').forEach((el) => {
        el.addEventListener('change', () => el.form.submit());
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\control_acceso\nube\resources\views/admin/llegadas-tarde/index.blade.php ENDPATH**/ ?>