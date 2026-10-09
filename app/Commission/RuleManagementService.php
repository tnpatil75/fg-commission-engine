<?php

namespace App\Commission;

use App\Commission\Data\RuleVersionInput;
use App\Models\Company;
use App\Models\ProductSegment;
use App\Models\Region;
use App\Models\RuleCombination;
use App\Models\RuleVersion;
use App\Models\SaleType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates, versions and deactivates rules. Versions are never edited: every change inserts a new version
 * and moves the combination's active pointer, so historical calculations keep pointing at what they used.
 */
final class RuleManagementService
{
    public function createRule(
        string $segmentCode,
        string $regionCode,
        string $leadCompanyCode,
        string $saleTypeCode,
        RuleVersionInput $input,
    ): RuleVersion {
        $dimensions = [
            'product_segment_id' => $this->id(ProductSegment::class, $segmentCode, 'product segment'),
            'region_id' => $this->id(Region::class, $regionCode, 'region'),
            'lead_company_id' => $this->id(Company::class, $leadCompanyCode, 'lead company'),
            'sale_type_id' => $this->id(SaleType::class, $saleTypeCode, 'sale type'),
        ];

        return DB::transaction(function () use ($dimensions, $input) {
            $combination = RuleCombination::query()->where($dimensions)->lockForUpdate()->first();

            if ($combination?->isActive()) {
                $active = $combination->activeVersion;
                throw new BusinessRuleViolation(
                    "An active rule already exists for this combination ({$active->reference()}). "
                    .'Create a new version of that rule instead.'
                );
            }

            if ($combination === null) {
                try {
                    $combination = RuleCombination::create($dimensions);
                } catch (UniqueConstraintViolationException) {
                    throw new BusinessRuleViolation('A rule for this combination was created at the same time. Reload and create a new version instead.');
                }
            }

            return $this->appendVersion($combination, $input);
        });
    }

    /** Replaces the active version (or reactivates an inactive rule) with a new immutable version. */
    public function createVersion(RuleCombination $combination, RuleVersionInput $input): RuleVersion
    {
        return DB::transaction(function () use ($combination, $input) {
            $locked = RuleCombination::query()->whereKey($combination->id)->lockForUpdate()->firstOrFail();

            return $this->appendVersion($locked, $input);
        });
    }

    public function deactivate(RuleCombination $combination): void
    {
        DB::transaction(function () use ($combination) {
            $locked = RuleCombination::query()->whereKey($combination->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw new BusinessRuleViolation('This rule is already inactive.');
            }

            $locked->update(['active_rule_version_id' => null, 'deactivated_at' => now()]);
        });
    }

    private function appendVersion(RuleCombination $combination, RuleVersionInput $input): RuleVersion
    {
        $version = RuleVersion::create([
            'rule_combination_id' => $combination->id,
            'version' => (int) $combination->versions()->max('version') + 1,
            'kind' => $input->kind,
            'rate_bps' => $input->rateBps,
            'payer_company_id' => $input->payerCode === null ? null : $this->id(Company::class, $input->payerCode, 'payer'),
            'payee_company_id' => $input->payeeCode === null ? null : $this->id(Company::class, $input->payeeCode, 'payee'),
            'note' => $input->note,
        ]);

        $combination->update(['active_rule_version_id' => $version->id, 'deactivated_at' => null]);

        return $version;
    }

    /** @param  class-string<\App\Models\MasterRecord>  $model */
    private function id(string $model, string $code, string $label): int
    {
        return $model::query()->where('code', $code)->value('id')
            ?? throw new BusinessRuleViolation("Unknown {$label} code: {$code}.");
    }
}
