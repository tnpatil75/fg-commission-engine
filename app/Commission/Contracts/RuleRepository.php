<?php

namespace App\Commission\Contracts;

use App\Models\RuleVersion;

/**
 * Finds the active rule version for an exact combination of the four matching dimensions.
 * There are no wildcards or priorities: either one exact active rule exists, or none does.
 */
interface RuleRepository
{
    public function findActiveVersion(int $segmentId, int $regionId, int $leadCompanyId, int $saleTypeId): ?RuleVersion;
}
