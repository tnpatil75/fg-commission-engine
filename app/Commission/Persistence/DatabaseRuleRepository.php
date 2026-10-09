<?php

namespace App\Commission\Persistence;

use App\Commission\Contracts\RuleRepository;
use App\Models\RuleCombination;
use App\Models\RuleVersion;

final class DatabaseRuleRepository implements RuleRepository
{
    public function findActiveVersion(int $segmentId, int $regionId, int $leadCompanyId, int $saleTypeId): ?RuleVersion
    {
        $combination = RuleCombination::query()
            ->where('product_segment_id', $segmentId)
            ->where('region_id', $regionId)
            ->where('lead_company_id', $leadCompanyId)
            ->where('sale_type_id', $saleTypeId)
            ->whereNotNull('active_rule_version_id')
            ->with(['activeVersion.payer', 'activeVersion.payee', 'segment', 'region', 'leadCompany', 'saleType'])
            ->first();

        $version = $combination?->activeVersion;
        $version?->setRelation('combination', $combination);

        return $version;
    }
}
