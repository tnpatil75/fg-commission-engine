{{-- Text input. Expects: $form (ID prefix), $name, $label, optional $hint, $placeholder, $inputmode,
     $validate (client-side rule metadata rendered as data-* attributes; see public/js/form-validation.js). --}}
@php
    $id = $form.'-'.$name;
    $validate ??= [];
    $describedBy = trim((isset($hint) ? $id.'-hint ' : '').($errors->has($name) ? $id.'-error' : ''));
    $current = old($name, $value ?? '');
@endphp
<div class="field" data-field="{{ $name }}"
     @foreach ($validate as $rule => $setting) data-{{ $rule }}="{{ $setting === true ? '' : $setting }}" @endforeach>
    <label for="{{ $id }}">{{ $label }}</label>
    <input type="text" id="{{ $id }}" name="{{ $name }}" value="{{ $current }}"
           @isset($placeholder) placeholder="{{ $placeholder }}" @endisset
           @isset($inputmode) inputmode="{{ $inputmode }}" @endisset
           @if (! empty($validate['required']) && ! isset($validate['only-when'])) aria-required="true" @endif
           @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
           @error($name) aria-invalid="true" @enderror>
    @isset($hint)
        <small class="hint" id="{{ $id }}-hint">{{ $hint }}</small>
    @endisset
    @error($name)
        <small class="error" id="{{ $id }}-error">{{ $message }}</small>
    @enderror
</div>
