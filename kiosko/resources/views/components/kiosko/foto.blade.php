@props([
    'src' => null,
    'size' => 'md',
])

<div {{ $attributes->class(['photo', 'photo-sm' => $size === 'sm']) }}>
    @if ($src)
        <img src="{{ $src }}" alt="Foto del empleado">
    @else
        <span class="photo-note">foto de referencia<br>sin archivo</span>
    @endif
</div>
