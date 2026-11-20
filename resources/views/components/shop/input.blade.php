@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'bag' => 'default'])
@php
    $errorBag = $errors->getBag($bag);
    $id = $attributes->get('id', 'f-'.$name);
    $hasError = $errorBag->has($name);
@endphp
{{-- Floating-label text field. placeholder=" " lets CSS know when the field has text. --}}
<label class="field" for="{{ $id }}">
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" placeholder=" "
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           @required($required)
           @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
           {{ $attributes->except('id')->merge(['class' => 'field-input']) }}>
    <span class="field-label">{{ $label }}{{ $required ? ' *' : '' }}</span>
    @if ($hasError)
        <span id="{{ $id }}-error" class="field-error">{{ $errorBag->first($name) }}</span>
    @endif
</label>
