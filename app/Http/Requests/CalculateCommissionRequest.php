<?php

namespace App\Http\Requests;

use App\Commission\Data\CalculationInput;
use App\Commission\Money\Currency;
use App\Rules\DecimalAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared by the Calculate screen and POST /api/calculations. */
class CalculateCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Local demo: no authentication. Production needs authenticated, authorised access.
    }

    public function rules(): array
    {
        return [
            'segment' => ['required', 'string', Rule::exists('product_segments', 'code')],
            'region' => ['required', 'string', Rule::exists('regions', 'code')],
            'lead_company' => ['required', 'string', Rule::exists('companies', 'code')],
            'sale_type' => ['required', 'string', Rule::exists('sale_types', 'code')],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
            'amount' => ['bail', 'required', new DecimalAmount(Currency::tryFrom((string) $this->input('currency')))],
            'external_order_reference' => ['nullable', 'string', 'max:100'],
            'plant_reference' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency.enum' => 'The currency must be one of: '.implode(', ', Currency::codes()).'.',
            '*.exists' => 'The selected :attribute is not a known value.',
        ];
    }

    public function attributes(): array
    {
        return [
            'segment' => 'product segment',
            'region' => 'sale destination region',
            'lead_company' => 'lead-generating company',
            'sale_type' => 'sale type',
            'amount' => 'eligible sale amount',
        ];
    }

    public function toInput(): CalculationInput
    {
        $data = $this->validated();

        return CalculationInput::fromStrings(
            $data['segment'], $data['region'], $data['lead_company'], $data['sale_type'],
            $data['amount'], $data['currency'],
            $data['external_order_reference'] ?? null, $data['plant_reference'] ?? null,
        );
    }
}
