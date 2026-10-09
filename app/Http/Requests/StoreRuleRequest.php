<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** A new rule: the four exact matching dimensions plus the content of its first version. */
class StoreRuleRequest extends StoreRuleVersionRequest
{
    public function rules(): array
    {
        return [
            'segment' => ['required', 'string', Rule::exists('product_segments', 'code')],
            'region' => ['required', 'string', Rule::exists('regions', 'code')],
            'lead_company' => ['required', 'string', Rule::exists('companies', 'code')],
            'sale_type' => ['required', 'string', Rule::exists('sale_types', 'code')],
        ] + parent::rules();
    }

    public function attributes(): array
    {
        return [
            'segment' => 'product segment',
            'region' => 'sale destination region',
            'lead_company' => 'lead-generating company',
            'sale_type' => 'sale type',
        ];
    }
}
