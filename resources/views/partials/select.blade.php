{{-- Select bound to master codes. Expects: $form (ID prefix), $name, $label, $options (list of code/name), optional $hint,
     $placeholder, $validate (client-side rule metadata rendered as data-* attributes; see public/js/form-validation.js). --}}
@php
    $id = $form.'-'.$name;
    $validate ??= [];
    $describedBy = trim((isset($hint) ? $id.'-hint ' : '').($errors->has($name) ? $id.'-error' : ''));
    $selected = old($name, $value ?? null);
@endphp
<div class="field" data-field="{{ $name }}"
     @foreach ($validate as $rule => $setting) data-{{ $rule }}="{{ $setting === true ? '' : $setting }}" @endforeach>
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}"
            @if (! empty($validate['required']) && ! isset($validate['only-when'])) aria-required="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @error($name) aria-invalid="true" @enderror>
        <option value="">{{ $placeholder ?? 'Choose…' }}</option>
        @foreach ($options as $option)
            <option value="{{ $option['code'] }}" @selected($selected === $option['code'])>{{ $option['name'] }}</option>
        @endforeach
    </select>
    @isset($hint)
        <small class="hint" id="{{ $id }}-hint">{{ $hint }}</small>
    @endisset
    @error($name)
        <small class="error" id="{{ $id }}-error">{{ $message }}</small>
    @enderror
</div>
