<?php

namespace Database\Seeders;

use App\Commission\Data\RuleVersionInput;
use App\Commission\RuleManagementService;
use App\Models\RuleCombination;
use Illuminate\Database\Seeder;

/**
 * Invented sample rules. The percentages and payment directions are ILLUSTRATIVE ONLY and are not FG's
 * actual commercial terms.
 */
class IllustrativeRuleSeeder extends Seeder
{
    public const NOTE = 'Illustrative sample rule - not FG\'s actual commercial terms.';

    public function run(RuleManagementService $rules): void
    {
        if (RuleCombination::query()->exists()) {
            return;
        }

        // 1. Building Envelope / GCC / Partner / Glass only: fixed 5%, FG pays Partner.
        $rules->createRule('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY',
            RuleVersionInput::fixed(500, 'FG', 'PARTNER', self::NOTE));

        // 2. Protection / Europe / FG / Complete system: fixed 3%, Partner pays FG.
        $rules->createRule('PROTECTION', 'EUROPE', 'FG', 'COMPLETE_SYSTEM',
            RuleVersionInput::fixed(300, 'PARTNER', 'FG', self::NOTE));

        // 3. Specialty / India / Partner / Complete system: decided case by case (manual decision required).
        $rules->createRule('SPECIALTY', 'INDIA', 'PARTNER', 'COMPLETE_SYSTEM',
            RuleVersionInput::caseByCase(self::NOTE));

        // 4. Building Envelope / India / FG / Glass only: explicit fixed 0%, FG pays Partner (zero amount).
        $rules->createRule('BUILDING_ENVELOPE', 'INDIA', 'FG', 'GLASS_ONLY',
            RuleVersionInput::fixed(0, 'FG', 'PARTNER', self::NOTE));
    }
}
