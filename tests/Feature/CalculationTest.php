<?php

namespace Tests\Feature;

use App\Commission\BusinessRuleViolation;
use App\Commission\Outcome;
use App\Models\AuditRecord;
use App\Models\Calculation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class CalculationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_fixed_rule_where_fg_pays_partner(): void
    {
        $result = $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY', '100000.00', 'AED');

        $this->assertSame(Outcome::Fixed, $result->outcome);
        $this->assertSame(500, $result->rateBps);
        $this->assertSame(500_000, $result->commissionMinor);
        $this->assertSame('AED 5,000.00', $result->commissionDisplay());
        $this->assertSame('FG', $result->payer['code']);
        $this->assertSame('PARTNER', $result->payee['code']);
        $this->assertSame(1, $result->rule['version']);
        $this->assertStringContainsString('paid by FG to Partner', $result->explanation);
    }

    public function test_fixed_rule_where_partner_pays_fg_rounds_half_up_once(): void
    {
        $result = $this->calculate('PROTECTION', 'EUROPE', 'FG', 'COMPLETE_SYSTEM', '2500.50', 'EUR');

        $this->assertSame(Outcome::Fixed, $result->outcome);
        $this->assertSame(7_502, $result->commissionMinor, '3% of 2,500.50 = 75.015, rounded half up to 75.02');
        $this->assertSame('PARTNER', $result->payer['code']);
        $this->assertSame('FG', $result->payee['code']);
    }

    public function test_explicit_zero_percent_is_a_fixed_outcome_not_a_missing_rule(): void
    {
        $result = $this->calculate('BUILDING_ENVELOPE', 'INDIA', 'FG', 'GLASS_ONLY', '5000', 'INR');

        $this->assertSame(Outcome::Fixed, $result->outcome);
        $this->assertFalse($result->isPending());
        $this->assertSame(0, $result->rateBps);
        $this->assertSame(0, $result->commissionMinor);
        $this->assertSame('FG', $result->payer['code']);
        $this->assertSame('PARTNER', $result->payee['code']);
        $this->assertNotNull($result->rule);
        $this->assertStringContainsString('explicitly configures 0%', $result->explanation);
    }

    public function test_case_by_case_rule_is_pending_with_no_rate_amount_or_direction(): void
    {
        $result = $this->calculate('SPECIALTY', 'INDIA', 'PARTNER', 'COMPLETE_SYSTEM', '750000', 'INR');

        $this->assertSame(Outcome::CaseByCase, $result->outcome);
        $this->assertTrue($result->isPending());
        $this->assertNull($result->rateBps);
        $this->assertNull($result->commissionMinor);
        $this->assertNull($result->payer);
        $this->assertNull($result->payee);
        $this->assertSame('case_by_case', $result->rule['kind']);
        $this->assertStringContainsString('decided case by case', $result->explanation);
    }

    public function test_no_matching_rule_is_pending_and_never_treated_as_zero_percent(): void
    {
        $result = $this->calculate('SPECIALTY', 'GCC', 'FG', 'GLASS_ONLY', '10', 'USD');

        $this->assertSame(Outcome::NoMatch, $result->outcome);
        $this->assertTrue($result->isPending());
        $this->assertNull($result->rateBps);
        $this->assertNull($result->commissionMinor);
        $this->assertNull($result->payer);
        $this->assertNull($result->payee);
        $this->assertNull($result->rule);
        $this->assertStringContainsString('never treated as 0%', $result->explanation);
    }

    public function test_matching_is_exact_on_all_four_dimensions_with_no_fallback(): void
    {
        // Same as seeded rule 1 except one dimension each time.
        $this->assertSame(Outcome::NoMatch, $this->calculate('BUILDING_ENVELOPE', 'GCC', 'FG', 'GLASS_ONLY')->outcome);
        $this->assertSame(Outcome::NoMatch, $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'COMPLETE_SYSTEM')->outcome);
        $this->assertSame(Outcome::NoMatch, $this->calculate('BUILDING_ENVELOPE', 'EUROPE', 'PARTNER', 'GLASS_ONLY')->outcome);
        $this->assertSame(Outcome::NoMatch, $this->calculate('PROTECTION', 'GCC', 'PARTNER', 'GLASS_ONLY')->outcome);
    }

    public function test_plant_reference_is_recorded_but_does_not_affect_matching(): void
    {
        $withoutPlant = $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY', '1000.00', 'AED');
        $plantA = $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY', '1000.00', 'AED', 'PLANT-A');
        $plantB = $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY', '1000.00', 'AED', 'PLANT-B', 'ORD-123');

        foreach ([$plantA, $plantB] as $result) {
            $this->assertSame($withoutPlant->rule['rule_id'], $result->rule['rule_id']);
            $this->assertSame($withoutPlant->commissionMinor, $result->commissionMinor);
        }
        $this->assertStringContainsString('Plant reference PLANT-B is recorded for context', $plantB->explanation);

        $stored = Calculation::where('uuid', $plantB->calculationId)->firstOrFail();
        $this->assertSame('PLANT-B', $stored->plant_reference);
        $this->assertSame('ORD-123', $stored->external_order_reference);
    }

    public function test_each_calculation_writes_one_audit_record_explaining_the_rule(): void
    {
        $result = $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY', '1234.56', 'USD', 'PLANT-A');
        $calculation = Calculation::where('uuid', $result->calculationId)->firstOrFail();

        $this->assertCount(1, $calculation->auditRecords);
        $audit = $calculation->auditRecords->first();

        $this->assertSame(AuditRecord::EVENT_CALCULATED, $audit->event);
        $this->assertSame('rounding-v1', $audit->policy_version);
        $this->assertSame($result->explanation, $audit->explanation);
        $this->assertSame(['code' => 'GCC', 'name' => 'GCC'], $audit->input_snapshot['region']);
        $this->assertSame('1234.56', $audit->input_snapshot['eligible_amount']);
        $this->assertSame(123_456, $audit->input_snapshot['eligible_amount_minor']);
        $this->assertSame('PLANT-A', $audit->input_snapshot['plant_reference']);
        $this->assertSame(1, $audit->rule_snapshot['version']);
        $this->assertSame('5.00', $audit->rule_snapshot['rate_percent']);
        $this->assertSame('61.73', $audit->result_snapshot['commission_amount'], '5% of 1,234.56 = 61.728 -> 61.73');
        $this->assertSame('FG', $audit->result_snapshot['payer']['code']);
    }

    public function test_calculation_is_rolled_back_when_the_audit_record_cannot_be_written(): void
    {
        $calculationsBefore = Calculation::count();
        Event::listen('eloquent.creating: '.AuditRecord::class, function () {
            throw new RuntimeException('Simulated audit storage failure');
        });

        try {
            $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY');
            $this->fail('The calculation should fail when its audit record fails.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated audit storage failure', $e->getMessage());
        }

        $this->assertSame($calculationsBefore, Calculation::count());
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_unknown_master_codes_are_rejected_by_the_service(): void
    {
        $this->expectException(BusinessRuleViolation::class);
        $this->expectExceptionMessage('Unknown region code: ANTARCTICA');

        $this->calculate('BUILDING_ENVELOPE', 'ANTARCTICA', 'PARTNER', 'GLASS_ONLY');
    }

    public function test_calculations_and_audit_records_are_immutable(): void
    {
        $result = $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY');
        $calculation = Calculation::where('uuid', $result->calculationId)->firstOrFail();

        foreach ([fn () => $calculation->update(['rate_bps' => 900]), fn () => $calculation->delete(),
            fn () => $calculation->originalAudit->update(['explanation' => 'changed'])] as $attempt) {
            try {
                $attempt();
                $this->fail('Immutable record was changed.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(500, $calculation->fresh()->rate_bps);
    }

    public function test_database_check_constraints_reject_inconsistent_calculation_rows(): void
    {
        $pending = Calculation::where('uuid', $this->calculate('SPECIALTY', 'GCC', 'FG', 'GLASS_ONLY')->calculationId)->firstOrFail();
        $fixed = Calculation::where('uuid', $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY')->calculationId)->firstOrFail();

        $violations = [
            'pending outcome with a rate' => fn () => DB::table('calculations')->where('id', $pending->id)->update(['rate_bps' => 500]),
            'fixed outcome without amount' => fn () => DB::table('calculations')->where('id', $fixed->id)->update(['commission_minor' => null]),
            'payer equals payee' => fn () => DB::table('calculations')->where('id', $fixed->id)->update(['payee_company_id' => $fixed->payer_company_id]),
            'zero amount' => fn () => DB::table('calculations')->where('id', $fixed->id)->update(['eligible_amount_minor' => 0]),
            'unsupported currency' => fn () => DB::table('calculations')->where('id', $fixed->id)->update(['currency' => 'GBP']),
            'unknown outcome' => fn () => DB::table('calculations')->where('id', $fixed->id)->update(['outcome' => 'approved']),
        ];

        foreach ($violations as $label => $violation) {
            try {
                $violation();
                $this->fail("The database accepted: {$label}.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
