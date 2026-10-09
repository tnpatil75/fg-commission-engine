<?php

namespace App\Http\Requests;

use App\Commission\Data\ManualDecisionInput;
use App\Commission\Money\MoneyCalculator;
use App\Rules\PercentageRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordManualDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Demo only: the approver name is self-declared, not an authenticated identity.
    }

    public function rules(): array
    {
        return [
            'rate' => ['bail', 'required', new PercentageRate],
            'payer' => ['required', 'string', Rule::exists('companies', 'code')],
            'payee' => ['required', 'string', Rule::exists('companies', 'code'), 'different:payer'],
            'approver_name' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'payee.different' => 'The payer and payee must be different companies.',
            'reason.required' => 'A reason is mandatory for a manual decision.',
        ];
    }

    public function toInput(): ManualDecisionInput
    {
        $data = $this->validated();

        return new ManualDecisionInput(
            MoneyCalculator::parseRate($data['rate']), $data['payer'], $data['payee'],
            $data['approver_name'], $data['reason'],
        );
    }
}
