@props([
    'target',
    'ok' => false,
    'compact' => false,
])

<div class="keypad {{ $compact ? 'keypad-compact' : '' }}" data-target="{{ $target }}">
    @foreach (['1','2','3','4','5','6','7','8','9','C','0','⌫'] as $key)
        <button type="button" class="key" data-key="{{ $key }}">{{ $key }}</button>
    @endforeach
    @if ($ok)
        <button type="submit" class="key is-ok">OK</button>
    @endif
</div>
