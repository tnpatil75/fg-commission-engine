<?php

namespace App\Http\Requests;

use App\Commission\Data\RuleVersionInput;
use App\Commission\Money\MoneyCalculator;
use App\Commission\RuleKind;
use App\Rules\PercentageRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Content of a new rule version: fixed percentage (with payer and payee) or decided case by case. */
class StoreRuleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Local demo: no authentication. Production needs role-based permission to change rules.
    }

    public function rules(): array
    {
        $fixed = RuleKind::Fixed->value;
        $caseByCase = RuleKind::CaseByCase->value;

        return [
            'kind' => ['required', Rule::enum(RuleKind::class)],
            'rate' => ['bail', "required_if:kind,{$fixed}", "prohibited_if:kind,{$caseByCase}", 'nullable', new PercentageRate],
            'payer' => ["required_if:kind,{$fixed}", "prohibited_if:kind,{$caseByCase}", 'nullable', 'string', Rule::exists('companies', 'code')],
            'payee' => ["required_if:kind,{$fixed}", "prohibited_if:kind,{$caseByCase}", 'nullable', 'string', Rule::exists('companies', 'code'), 'different:payer'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'rate.required_if' => 'A fixed rule needs a rate (use 0 for an explicit zero rate).',
            'payer.required_if' => 'A fixed rule needs a payer.',
            'payee.required_if' => 'A fixed rule needs a payee.',
            '*.prohibited_if' => 'A case-by-case rule must not set a rate, payer or payee.',
            'payee.different' => 'The payer and payee must be different companies.',
        ];
    }

    public function toVersionInput(): RuleVersionInput
    {
        $data = $this->validated();
        $kind = RuleKind::from($data['kind']);

        if ($kind === RuleKind::CaseByCase) {
            return RuleVersionInput::caseByCase($data['note'] ?? null);
        }

        return RuleVersionInput::fixed(
            MoneyCalculator::parseRate($data['rate']), $data['payer'], $data['payee'], $data['note'] ?? null,
        );
    }
}
