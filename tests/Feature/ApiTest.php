<?php

namespace Tests\Feature;

use App\Commission\CommissionService;
use App\Commission\Data\ManualDecisionInput;
use App\Models\Calculation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const VALID = [
        'segment' => 'BUILDING_ENVELOPE',
        'region' => 'GCC',
        'lead_company' => 'PARTNER',
        'sale_type' => 'GLASS_ONLY',
        'amount' => '100000.00',
        'currency' => 'AED',
    ];

    public function test_post_calculation_returns_the_fixed_result(): void
    {
        $response = $this->postJson('/api/calculations', self::VALID + ['external_order_reference' => 'DEMO-ORD-1']);

        $response->assertCreated()
            ->assertJsonPath('data.outcome', 'fixed')
            ->assertJsonPath('data.pending_manual_decision', false)
            ->assertJsonPath('data.eligible_amount', '100000.00')
            ->assertJsonPath('data.currency', 'AED')
            ->assertJsonPath('data.rate_percent', '5.00')
            ->assertJsonPath('data.rate_bps', 500)
            ->assertJsonPath('data.commission_amount', '5000.00')
            ->assertJsonPath('data.commission_minor', 500000)
            ->assertJsonPath('data.payer.code', 'FG')
            ->assertJsonPath('data.payee.code', 'PARTNER')
            ->assertJsonPath('data.rule.version', 1)
            ->assertJsonPath('data.policy_version', 'rounding-v1');

        $this->assertDatabaseHas('calculations', ['uuid' => $response->json('data.calculation_id'), 'external_order_reference' => 'DEMO-ORD-1']);
    }

    public function test_pending_results_have_null_rate_amount_payer_and_payee(): void
    {
        foreach ([['SPECIALTY', 'INDIA', 'PARTNER', 'COMPLETE_SYSTEM', 'case_by_case'], ['SPECIALTY', 'GCC', 'FG', 'GLASS_ONLY', 'no_match']] as [$s, $r, $l, $t, $outcome]) {
            $this->postJson('/api/calculations', ['segment' => $s, 'region' => $r, 'lead_company' => $l, 'sale_type' => $t] + self::VALID)
                ->assertCreated()
                ->assertJsonPath('data.outcome', $outcome)
                ->assertJsonPath('data.pending_manual_decision', true)
                ->assertJsonPath('data.rate_percent', null)
                ->assertJsonPath('data.rate_bps', null)
                ->assertJsonPath('data.commission_amount', null)
                ->assertJsonPath('data.commission_minor', null)
                ->assertJsonPath('data.payer', null)
                ->assertJsonPath('data.payee', null);
        }
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_requests_are_rejected_and_nothing_is_stored(array $overrides, string $errorField): void
    {
        $payload = array_merge(self::VALID, $overrides);
        $payload = array_filter($payload, fn ($value) => $value !== '__missing__');

        $this->postJson('/api/calculations', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorField);

        $this->assertSame(0, Calculation::count());
    }

    public static function invalidPayloads(): array
    {
        return [
            'unknown segment' => [['segment' => 'WINDOWS'], 'segment'],
            'unknown region' => [['region' => 'MARS'], 'region'],
            'unknown lead company' => [['lead_company' => 'OTHER_CO'], 'lead_company'],
            'unknown sale type' => [['sale_type' => 'SERVICE'], 'sale_type'],
            'missing segment' => [['segment' => '__missing__'], 'segment'],
            'missing amount' => [['amount' => '__missing__'], 'amount'],
            'zero amount' => [['amount' => '0'], 'amount'],
            'negative amount' => [['amount' => '-100.00'], 'amount'],
            'over-precise amount' => [['amount' => '100.005'], 'amount'],
            'oversized amount' => [['amount' => '1000000000000.00'], 'amount'],
            'malformed amount' => [['amount' => '12abc'], 'amount'],
            'amount with separator' => [['amount' => '1,000.00'], 'amount'],
            'amount as JSON number' => [['amount' => 100], 'amount'],
            'amount as JSON float' => [['amount' => 0.1], 'amount'],
            'unsupported currency' => [['currency' => 'GBP'], 'currency'],
            'lowercase currency' => [['currency' => 'aed'], 'currency'],
            'missing currency' => [['currency' => '__missing__'], 'currency'],
            'overlong order reference' => [['external_order_reference' => str_repeat('x', 101)], 'external_order_reference'],
            'overlong plant reference' => [['plant_reference' => str_repeat('x', 51)], 'plant_reference'],
        ];
    }

    public function test_get_calculation_detail_distinguishes_original_outcome_from_manual_decision(): void
    {
        $id = $this->postJson('/api/calculations', ['segment' => 'SPECIALTY', 'region' => 'INDIA', 'sale_type' => 'COMPLETE_SYSTEM'] + self::VALID)
            ->json('data.calculation_id');

        $this->getJson("/api/calculations/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.original_outcome.outcome', 'case_by_case')
            ->assertJsonPath('data.manual_decision', null)
            ->assertJsonCount(1, 'data.audit_trail');

        app(CommissionService::class)->recordManualDecision($id, new ManualDecisionInput(150, 'FG', 'PARTNER', 'Demo Approver', 'Demo reason'));

        $this->getJson("/api/calculations/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'decided')
            ->assertJsonPath('data.original_outcome.outcome', 'case_by_case')
            ->assertJsonPath('data.original_outcome.rate_bps', null)
            ->assertJsonPath('data.manual_decision.rate_percent', '1.50')
            ->assertJsonPath('data.manual_decision.commission_amount', '1500.00')
            ->assertJsonPath('data.manual_decision.approver_name', 'Demo Approver')
            ->assertJsonCount(2, 'data.audit_trail')
            ->assertJsonPath('data.audit_trail.1.event', 'manual_decision');
    }

    public function test_unknown_calculation_returns_404_json(): void
    {
        $this->getJson('/api/calculations/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_masters_lists_the_configured_codes(): void
    {
        $response = $this->getJson('/api/masters')->assertOk();

        $this->assertSame(['BUILDING_ENVELOPE', 'PROTECTION', 'SPECIALTY'], array_column($response->json('data.segments'), 'code'));
        $this->assertSame(['GCC', 'EUROPE', 'INDIA', 'NORTH_AMERICA', 'REST_OF_WORLD'], array_column($response->json('data.regions'), 'code'));
        $this->assertSame(['FG', 'PARTNER'], array_column($response->json('data.companies'), 'code'));
        $this->assertSame(['GLASS_ONLY', 'COMPLETE_SYSTEM'], array_column($response->json('data.sale_types'), 'code'));
        $this->assertSame(['AED', 'USD', 'EUR', 'INR'], array_column($response->json('data.currencies'), 'code'));
    }
}
