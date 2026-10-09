{{-- Content of a rule version. Expects: $form (ID prefix), $masters, optional $values (current version, for updates). --}}
@use('App\Commission\Money\MoneyCalculator')
@php
    $values ??= [];
    $selectedKind = old('kind', $values['kind'] ?? 'fixed');
    $fixedOnly = ['required' => true, 'only-when' => 'kind=fixed'];
@endphp
<fieldset class="field radio-group" id="{{ $form }}-kind" data-field="kind" data-required>
    <legend>Rule type</legend>
    <label><input type="radio" id="{{ $form }}-kind-fixed" name="kind" value="fixed" @checked($selectedKind === 'fixed')
                  @error('kind') aria-invalid="true" aria-describedby="{{ $form }}-kind-error" @enderror> Fixed percentage (0% is allowed and explicit)</label>
    <label><input type="radio" id="{{ $form }}-kind-case_by_case" name="kind" value="case_by_case" @checked($selectedKind === 'case_by_case')
                  @error('kind') aria-invalid="true" aria-describedby="{{ $form }}-kind-error" @enderror> Decided case by case: manual approval required</label>
    @error('kind')<small class="error" id="{{ $form }}-kind-error">{{ $message }}</small>@enderror
</fieldset>
<p class="hint" data-no-js>For a case-by-case rule, leave rate, payer and payee empty.</p>
<p class="hint" data-show-when="kind=case_by_case" hidden>Payment terms will be decided manually for each matching calculation,
    so this version has no rate, payer or payee.</p>

@include('partials.input', ['form' => $form, 'name' => 'rate', 'label' => 'Rate (%)', 'inputmode' => 'decimal', 'placeholder' => 'e.g. 5',
    'value' => $values['rate'] ?? null,
    'hint' => 'Fixed rules only: 0 to 100, at most 2 decimal places. Leave empty for case by case.',
    'validate' => $fixedOnly + ['format' => 'percentage', 'max-bps' => MoneyCalculator::MAX_RATE_BPS,
        'required-message' => 'A fixed rule needs a rate (use 0 for an explicit zero rate).']])
@include('partials.select', ['form' => $form, 'name' => 'payer', 'label' => 'Payer', 'options' => $masters['companies'],
    'value' => $values['payer'] ?? null,
    'placeholder' => '— none (case by case) —', 'hint' => 'Configured explicitly; not inferred from who generated the lead.',
    'validate' => $fixedOnly + ['required-message' => 'A fixed rule needs a payer.']])
@include('partials.select', ['form' => $form, 'name' => 'payee', 'label' => 'Payee', 'options' => $masters['companies'],
    'value' => $values['payee'] ?? null,
    'placeholder' => '— none (case by case) —',
    'validate' => $fixedOnly + ['required-message' => 'A fixed rule needs a payee.', 'different-from' => 'payer',
        'different-message' => 'The payer and payee must be different companies.']])
@include('partials.input', ['form' => $form, 'name' => 'note', 'label' => 'Note (optional)', 'placeholder' => 'e.g. Illustrative sample rule',
    'value' => $values['note'] ?? null,
    'validate' => ['maxlength' => 255]])
