<?php

namespace App\Commission;

use App\Commission\Money\Currency;
use App\Models\Company;
use App\Models\ProductSegment;
use App\Models\Region;
use App\Models\SaleType;

/**
 * Master values for form options and the API listing.
 */
final class MasterData
{
    /** @return array<string, list<array{code: string, name: string}>> */
    public function all(): array
    {
        return [
            'segments' => $this->list(ProductSegment::class),
            'regions' => $this->list(Region::class),
            'companies' => $this->list(Company::class),
            'sale_types' => $this->list(SaleType::class),
            'currencies' => array_map(fn (string $code) => ['code' => $code, 'name' => $code], Currency::codes()),
        ];
    }

    /** @param  class-string<\App\Models\MasterRecord>  $model */
    private function list(string $model): array
    {
        return $model::query()->orderBy('id')->get(['code', 'name'])
            ->map(fn ($row) => ['code' => $row->code, 'name' => $row->name])
            ->all();
    }
}
