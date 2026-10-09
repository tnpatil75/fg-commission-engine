<?php

namespace Tests\Feature;

use App\Commission\BusinessRuleViolation;
use App\Commission\CommissionService;
use App\Commission\Data\ManualDecisionInput;
use App\Commission\Outcome;
use App\Models\AuditRecord;
use App\Models\Calculation;
use App\Models\Company;
use App\Models\ManualDecision;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class ManualDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function pendingCaseByCase(string $amount = '1234.56'): Calculation
    {
        $result = $this->calculate('SPECIALTY', 'INDIA', 'PARTNER', 'COMPLETE_SYSTEM', $amount, 'USD');

        return Calculation::where('uuid', $result->calculationId)->firstOrFail();
    }

    private function decide(Calculation $calculation, int $rateBps = 250, string $payer = 'FG', string $payee = 'PARTNER'): ManualDecision
    {
        return app(CommissionService::class)->recordManualDecision(
            $calculation->uuid,
            new ManualDecisionInput($rateBps, $payer, $payee, 'Demo Approver', 'Illustrative strategic project terms.'),
        );
    }

    public function test_a_decision_on_a_case_by_case_calculation_is_appended_without_changing_the_original(): void
    {
        $calculation = $this->pendingCaseByCase('1234.56');

        $decision = $this->decide($calculation, 250);

        $this->assertSame(3_086, $decision->commission_minor, '2.5% of 1,234.56 = 30.864 -> 30.86');
        $this->assertSame('USD', $decision->currency->value);
        $this->assertSame('Demo Approver', $decision->approver_name);

        $fresh = $calculation->fresh();
        $this->assertSame(Outcome::CaseByCase, $fresh->outcome);
        $this->assertNull($fresh->rate_bps);
        $this->assertNull($fresh->commission_minor);
        $this->assertNull($fresh->payer_company_id);
        $this->assertSame('decided', $fresh->status());

        $audits = $fresh->auditRecords;
        $this->assertSame([AuditRecord::EVENT_CALCULATED, AuditRecord::EVENT_MANUAL_DECISION], $audits->pluck('event')->all());
        $decisionAudit = $audits->last();
        $this->assertSame($decision->id, $decisionAudit->manual_decision_id);
        $this->assertSame($audits->first()->input_snapshot, $decisionAudit->input_snapshot);
        $this->assertSame('30.86', $decisionAudit->result_snapshot['commission_amount']);
        $this->assertSame('self-declared (not authenticated in this demo)', $decisionAudit->result_snapshot['decision']['approver_identity']);
        $this->assertStringContainsString('is preserved unchanged', $decisionAudit->explanation);
        $this->assertStringContainsString('Illustrative strategic project terms.', $decisionAudit->explanation);
        $this->assertNull($audits->first()->result_snapshot['rate_bps'], 'The original audit still shows no rate.');
    }

    public function test_a_decision_on_a_no_match_calculation_rounds_half_up(): void
    {
        $result = $this->calculate('SPECIALTY', 'GCC', 'FG', 'GLASS_ONLY', '10.00', 'USD');
        $calculation = Calculation::where('uuid', $result->calculationId)->firstOrFail();

        $decision = $this->decide($calculation, 5, 'PARTNER', 'FG');

        $this->assertSame(1, $decision->commission_minor, '0.05% of 10.00 = 0.005 -> 0.01');
        $this->assertSame(Outcome::NoMatch, $calculation->fresh()->outcome);
    }

    public function test_an_explicit_zero_percent_decision_is_allowed(): void
    {
        $decision = $this->decide($this->pendingCaseByCase(), 0);

        $this->assertSame(0, $decision->commission_minor);
    }

    public function test_a_second_decision_is_rejected(): void
    {
        $calculation = $this->pendingCaseByCase();
        $this->decide($calculation);

        try {
            $this->decide($calculation, 900);
            $this->fail('A second decision was recorded.');
        } catch (BusinessRuleViolation $e) {
            $this->assertStringContainsString('already been recorded', $e->getMessage());
        }

        $this->assertSame(1, ManualDecision::where('calculation_id', $calculation->id)->count());
        $this->assertSame(250, $calculation->manualDecision->rate_bps);
        $this->assertSame(2, $calculation->auditRecords()->count());
    }

    public function test_a_concurrent_decision_that_wins_the_race_is_detected_by_the_database(): void
    {
        $calculation = $this->pendingCaseByCase();
        $companies = Company::pluck('id', 'code');

        // Simulates another request inserting its decision after this request's checks but before its insert.
        Event::listen('eloquent.creating: '.ManualDecision::class, function () use ($calculation, $companies) {
            DB::table('manual_decisions')->insert([
                'uuid' => (string) str()->uuid(), 'calculation_id' => $calculation->id, 'rate_bps' => 100,
                'commission_minor' => 1_235, 'currency' => 'USD', 'payer_company_id' => $companies['FG'],
                'payee_company_id' => $companies['PARTNER'], 'approver_name' => 'Other request',
                'reason' => 'Concurrent submission', 'policy_version' => 'rounding-v1',
            ]);
        });

        $this->expectException(BusinessRuleViolation::class);
        $this->expectExceptionMessage('already been recorded');

        $this->decide($calculation);
    }

    public function test_the_database_allows_only_one_decision_per_calculation(): void
    {
        $calculation = $this->pendingCaseByCase();
        $this->decide($calculation);
        $existing = DB::table('manual_decisions')->where('calculation_id', $calculation->id)->first();

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('manual_decisions')->insert(array_merge((array) $existing, ['id' => null, 'uuid' => (string) str()->uuid()]));
    }

    public function test_decisions_are_not_allowed_on_fixed_calculations(): void
    {
        foreach ([['BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY'], ['BUILDING_ENVELOPE', 'INDIA', 'FG', 'GLASS_ONLY']] as $dimensions) {
            $result = $this->calculate(...$dimensions);
            $calculation = Calculation::where('uuid', $result->calculationId)->firstOrFail();

            try {
                $this->decide($calculation);
                $this->fail('A decision was recorded on a fixed calculation.');
            } catch (BusinessRuleViolation $e) {
                $this->assertStringContainsString('only allowed for pending calculations', $e->getMessage());
            }
        }

        $this->assertSame(0, ManualDecision::count());
    }

    public function test_decision_input_is_validated(): void
    {
        $attempts = [
            'rate above 100%' => fn () => new ManualDecisionInput(10_001, 'FG', 'PARTNER', 'A', 'R'),
            'negative rate' => fn () => new ManualDecisionInput(-1, 'FG', 'PARTNER', 'A', 'R'),
            'payer equals payee' => fn () => new ManualDecisionInput(100, 'FG', 'FG', 'A', 'R'),
            'blank approver' => fn () => new ManualDecisionInput(100, 'FG', 'PARTNER', '  ', 'R'),
            'blank reason' => fn () => new ManualDecisionInput(100, 'FG', 'PARTNER', 'A', ' '),
        ];

        foreach ($attempts as $label => $attempt) {
            try {
                $attempt();
                $this->fail("Accepted invalid decision: {$label}.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_database_rejects_a_decision_where_payer_equals_payee(): void
    {
        $calculation = $this->pendingCaseByCase();
        $fg = Company::where('code', 'FG')->value('id');

        $this->expectException(QueryException::class);

        DB::table('manual_decisions')->insert([
            'uuid' => (string) str()->uuid(), 'calculation_id' => $calculation->id, 'rate_bps' => 100,
            'commission_minor' => 1, 'currency' => 'USD', 'payer_company_id' => $fg, 'payee_company_id' => $fg,
            'approver_name' => 'A', 'reason' => 'R', 'policy_version' => 'rounding-v1',
        ]);
    }

    public function test_decisions_are_immutable(): void
    {
        $decision = $this->decide($this->pendingCaseByCase());

        $this->expectException(LogicException::class);

        $decision->update(['rate_bps' => 900]);
    }
}
