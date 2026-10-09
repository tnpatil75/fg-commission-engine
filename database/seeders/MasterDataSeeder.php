<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ProductSegment;
use App\Models\Region;
use App\Models\SaleType;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public const SEGMENTS = [
        'BUILDING_ENVELOPE' => 'Building Envelope',
        'PROTECTION' => 'Protection',
        'SPECIALTY' => 'Specialty',
    ];

    public const REGIONS = [
        'GCC' => 'GCC',
        'EUROPE' => 'Europe',
        'INDIA' => 'India',
        'NORTH_AMERICA' => 'North America',
        'REST_OF_WORLD' => 'Rest of World',
    ];

    public const COMPANIES = [
        'FG' => 'FG',
        'PARTNER' => 'Partner',
    ];

    public const SALE_TYPES = [
        'GLASS_ONLY' => 'Glass only',
        'COMPLETE_SYSTEM' => 'Complete system',
    ];

    public function run(): void
    {
        $this->seed(ProductSegment::class, self::SEGMENTS);
        $this->seed(Region::class, self::REGIONS);
        $this->seed(Company::class, self::COMPANIES);
        $this->seed(SaleType::class, self::SALE_TYPES);
    }

    /** @param  class-string<\App\Models\MasterRecord>  $model */
    private function seed(string $model, array $values): void
    {
        foreach ($values as $code => $name) {
            $model::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
