<?php

namespace Tests\Feature;

use App\Commission\BusinessRuleViolation;
use App\Commission\Data\RuleVersionInput;
use App\Commission\Outcome;
use App\Commission\RuleManagementService;
use App\Models\AuditRecord;
use App\Models\Calculation;
use App\Models\Company;
use App\Models\ProductSegment;
use App\Models\Region;
use App\Models\RuleCombination;
use App\Models\RuleVersion;
use App\Models\SaleType;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class RuleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function rules(): RuleManagementService
    {
        return app(RuleManagementService::class);
    }

    private function combination(string $segment, string $region, string $lead, string $saleType): RuleCombination
    {
        return RuleCombination::query()->where([
            'product_segment_id' => ProductSegment::where('code', $segment)->value('id'),
            'region_id' => Region::where('code', $region)->value('id'),
            'lead_company_id' => Company::where('code', $lead)->value('id'),
            'sale_type_id' => SaleType::where('code', $saleType)->value('id'),
        ])->firstOrFail();
    }

    public function test_seeder_creates_the_four_illustrative_rules(): void
    {
        $this->assertSame(4, RuleCombination::count());
        $this->assertSame(4, RuleVersion::count());
        $this->assertSame(500, $this->combination('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY')->activeVersion->rate_bps);
        $this->assertSame(300, $this->combination('PROTECTION', 'EUROPE', 'FG', 'COMPLETE_SYSTEM')->activeVersion->rate_bps);
        $this->assertNull($this->combination('SPECIALTY', 'INDIA', 'PARTNER', 'COMPLETE_SYSTEM')->activeVersion->rate_bps);
        $this->assertSame(0, $this->combination('BUILDING_ENVELOPE', 'INDIA', 'FG', 'GLASS_ONLY')->activeVersion->rate_bps);
    }

    public function test_a_new_rule_is_matched_immediately_from_the_database(): void
    {
        $this->rules()->createRule('SPECIALTY', 'NORTH_AMERICA', 'PARTNER', 'GLASS_ONLY', RuleVersionInput::fixed(125, 'FG', 'PARTNER'));

        $result = $this->calculate('SPECIALTY', 'NORTH_AMERICA', 'PARTNER', 'GLASS_ONLY', '10000.00', 'USD');

        $this->assertSame(Outcome::Fixed, $result->outcome);
        $this->assertSame(12_500, $result->commissionMinor);
    }

    public function test_a_second_active_rule_for_the_same_combination_is_rejected(): void
    {
        try {
            $this->rules()->createRule('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY', RuleVersionInput::fixed(700, 'FG', 'PARTNER'));
            $this->fail('A duplicate active rule was created.');
        } catch (BusinessRuleViolation $e) {
            $this->assertStringContainsString('An active rule already exists', $e->getMessage());
        }

        $this->assertSame(4, RuleCombination::count());
        $this->assertSame(4, RuleVersion::count());
    }

    public function test_the_database_rejects_duplicate_combinations(): void
    {
        $existing = $this->combination('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY');

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('rule_combinations')->insert($existing->only(['product_segment_id', 'region_id', 'lead_company_id', 'sale_type_id']));
    }

    public function test_the_active_pointer_cannot_reference_another_combinations_version(): void
    {
        $first = $this->combination('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY');
        $other = $this->combination('PROTECTION', 'EUROPE', 'FG', 'COMPLETE_SYSTEM');

        $this->expectException(QueryException::class);

        DB::table('rule_combinations')->where('id', $first->id)->update(['active_rule_version_id' => $other->active_rule_version_id]);
    }

    public function test_a_new_version_replaces_the_active_one_and_keeps_history(): void
    {
        $combination = $this->combination('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY');
        $v1 = $combination->activeVersion;

        $v2 = $this->rules()->createVersion($combination, RuleVersionInput::fixed(650, 'FG', 'PARTNER', 'Revised illustrative rate'));

        $this->assertSame(2, $v2->version);
        $this->assertSame($v2->id, $combination->fresh()->active_rule_version_id);
        $this->assertSame(500, $v1->fresh()->rate_bps, 'The previous version is unchanged.');
        $this->assertSame(2, $combination->versions()->count());
        $this->assertSame(650, $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY')->rateBps);
    }

    public function test_a_rule_can_change_from_fixed_to_case_by_case_through_a_new_version(): void
    {
        $combination = $this->combination('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY');

        $this->rules()->createVersion($combination, RuleVersionInput::caseByCase());

        $this->assertSame(Outcome::CaseByCase, $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY')->outcome);
    }

    public function test_deactivation_leads_to_no_match_and_a_rule_can_be_reactivated_with_a_new_version(): void
    {
        $combination = $this->combination('PROTECTION', 'EUROPE', 'FG', 'COMPLETE_SYSTEM');

        $this->rules()->deactivate($combination);

        $this->assertFalse($combination->fresh()->isActive());
        $this->assertNotNull($combination->fresh()->deactivated_at);
        $this->assertSame(1, $combination->versions()->count(), 'Versions are kept after deactivation.');
        $this->assertSame(Outcome::NoMatch, $this->calculate('PROTECTION', 'EUROPE', 'FG', 'COMPLETE_SYSTEM')->outcome);

        try {
            $this->rules()->deactivate($combination->fresh());
            $this->fail('An inactive rule was deactivated twice.');
        } catch (BusinessRuleViolation) {
            $this->addToAssertionCount(1);
        }

        $v2 = $this->rules()->createRule('PROTECTION', 'EUROPE', 'FG', 'COMPLETE_SYSTEM', RuleVersionInput::fixed(350, 'PARTNER', 'FG'));

        $this->assertSame(2, $v2->version);
        $this->assertSame($combination->id, $v2->rule_combination_id);
        $this->assertNull($combination->fresh()->deactivated_at);
        $this->assertSame(350, $this->calculate('PROTECTION', 'EUROPE', 'FG', 'COMPLETE_SYSTEM')->rateBps);
    }

    public function test_historical_calculations_keep_their_snapshot_after_rule_and_label_changes(): void
    {
        $original = $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY', '1000.00', 'AED');
        $combination = $this->combination('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY');
        $v1Id = $combination->active_rule_version_id;

        $this->rules()->createVersion($combination, RuleVersionInput::fixed(900, 'PARTNER', 'FG'));
        $this->rules()->deactivate($combination->fresh());
        Region::where('code', 'GCC')->update(['name' => 'Gulf (renamed)']);
        Company::where('code', 'PARTNER')->update(['name' => 'Partner (renamed)']);

        $calculation = Calculation::where('uuid', $original->calculationId)->firstOrFail();
        $audit = $calculation->originalAudit;

        $this->assertSame($v1Id, $calculation->rule_version_id);
        $this->assertSame(500, $calculation->rate_bps);
        $this->assertSame(5_000, $calculation->commission_minor);
        $this->assertSame('GCC', $audit->input_snapshot['region']['name']);
        $this->assertSame('Partner', $audit->result_snapshot['payee']['name']);
        $this->assertSame('5.00', $audit->rule_snapshot['rate_percent']);
        $this->assertSame(1, $audit->rule_snapshot['version']);
        $this->assertSame($original->explanation, $audit->explanation);
    }

    public function test_rule_versions_are_immutable_and_referenced_versions_cannot_be_deleted(): void
    {
        $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY');
        $version = $this->combination('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY')->activeVersion;

        foreach ([fn () => $version->update(['rate_bps' => 900]), fn () => $version->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('A rule version was changed.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(QueryException::class);
        DB::table('rule_versions')->where('id', $version->id)->delete();
    }

    public function test_referenced_master_data_cannot_be_deleted(): void
    {
        $this->expectException(QueryException::class);

        DB::table('regions')->where('code', 'GCC')->delete();
    }

    public function test_database_check_constraint_rejects_inconsistent_rule_versions(): void
    {
        $combination = $this->combination('SPECIALTY', 'INDIA', 'PARTNER', 'COMPLETE_SYSTEM');
        $fg = Company::where('code', 'FG')->value('id');
        $base = ['rule_combination_id' => $combination->id, 'uuid' => (string) str()->uuid()];

        $violations = [
            'fixed without payer' => $base + ['version' => 10, 'kind' => 'fixed', 'rate_bps' => 100, 'payee_company_id' => $fg],
            'fixed above 100%' => $base + ['version' => 11, 'kind' => 'fixed', 'rate_bps' => 10_001,
                'payer_company_id' => $fg, 'payee_company_id' => Company::where('code', 'PARTNER')->value('id')],
            'fixed with payer equal to payee' => $base + ['version' => 12, 'kind' => 'fixed', 'rate_bps' => 100,
                'payer_company_id' => $fg, 'payee_company_id' => $fg],
            'case by case with rate' => $base + ['version' => 13, 'kind' => 'case_by_case', 'rate_bps' => 100],
            'unknown kind' => $base + ['version' => 14, 'kind' => 'tiered'],
        ];

        foreach ($violations as $label => $row) {
            try {
                DB::table('rule_versions')->insert($row);
                $this->fail("The database accepted: {$label}.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_rule_version_input_rejects_inconsistent_definitions(): void
    {
        $attempts = [
            fn () => RuleVersionInput::fixed(500, 'FG', 'FG'),
            fn () => RuleVersionInput::fixed(10_001, 'FG', 'PARTNER'),
            fn () => RuleVersionInput::fixed(-1, 'FG', 'PARTNER'),
            fn () => new RuleVersionInput(\App\Commission\RuleKind::CaseByCase, 100),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('An inconsistent rule definition was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_rule_changes_do_not_write_calculation_audit_records(): void
    {
        $this->rules()->createVersion($this->combination('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY'), RuleVersionInput::caseByCase());

        $this->assertSame(0, AuditRecord::count());
    }
}
