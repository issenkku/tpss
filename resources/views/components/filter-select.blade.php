@props([
    'name',
    'label' => null,
    'id' => null,
    'reset' => null,
    'disabled' => false,
    'fieldClass' => null,
    'labelClass' => null,
    'selectWrapperClass' => null,
])

@php
    $controlId = $id ?: 'filter-' . \Illuminate\Support\Str::slug($name);
@endphp

<div @class(['filter-select-field', $fieldClass])>
    <label for="{{ $controlId }}" @class(['filter-select-label', $labelClass])>
        {{ $labelContent ?? $label }}
    </label>

    @if($selectWrapperClass)
        <div class="{{ $selectWrapperClass }}">
    @endif

    <select id="{{ $controlId }}"
            name="{{ $name }}"
            data-async-filter-change
            @if($reset) data-async-filter-reset="{{ $reset }}" @endif
            @disabled($disabled)
            {{ $attributes->class('filter-select-control') }}>
        {{ $slot }}
    </select>

    @if($selectWrapperClass)
        </div>
    @endif
</div>
