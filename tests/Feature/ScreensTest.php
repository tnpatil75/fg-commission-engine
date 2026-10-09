<?php

namespace Tests\Feature;

use App\Models\Calculation;
use App\Models\ManualDecision;
use App\Models\RuleCombination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const FIXED = [
        'segment' => 'BUILDING_ENVELOPE', 'region' => 'GCC', 'lead_company' => 'PARTNER',
        'sale_type' => 'GLASS_ONLY', 'amount' => '100000.00', 'currency' => 'AED',
    ];

    public function test_calculate_screen_shows_the_form_with_masters_from_the_database(): void
    {
        $this->get('/')->assertRedirect('/calculate');

        $this->get('/calculate')->assertOk()
            ->assertSee('Building Envelope')
            ->assertSee('Rest of World')
            ->assertSee('Complete system')
            ->assertSee('Illustrative sample data');
    }

    public function test_fixed_result_shows_rate_amount_currency_payer_payee_and_explanation(): void
    {
        $this->followingRedirects()->post('/calculate', self::FIXED)->assertOk()
            ->assertSee('Fixed rule applied')
            ->assertSee('5.00%')
            ->assertSee('AED 5,000.00')
            ->assertSee('AED 100,000.00')
            ->assertSeeInOrder(['Payer', 'FG', 'Payee', 'Partner'])
            ->assertSee('paid by FG to Partner');
    }

    public function test_pending_result_shows_no_rate_or_amount(): void
    {
        $this->followingRedirects()->post('/calculate', ['segment' => 'SPECIALTY', 'region' => 'INDIA', 'sale_type' => 'COMPLETE_SYSTEM'] + self::FIXED)
            ->assertOk()
            ->assertSee('manual approval required')
            ->assertSee('Not set')
            ->assertDontSee('Commission / referral amount')
            ->assertSee('record a manual decision');
    }

    public function test_invalid_input_returns_to_the_form_with_errors(): void
    {
        $this->from('/calculate')->post('/calculate', ['amount' => '1.005'] + self::FIXED)
            ->assertRedirect('/calculate')
            ->assertSessionHasErrors(['amount' => 'The eligible sale amount must have at most 2 decimal places.']);

        $this->assertSame(0, Calculation::count());
    }

    public function test_rules_screen_lists_creates_versions_and_deactivates(): void
    {
        $this->get('/rules')->assertOk()->assertSee('Building Envelope')->assertSee('Decided case by case');
        $this->get('/rules/create')->assertOk();

        $this->post('/rules', [
            'segment' => 'PROTECTION', 'region' => 'NORTH_AMERICA', 'lead_company' => 'PARTNER', 'sale_type' => 'GLASS_ONLY',
            'kind' => 'fixed', 'rate' => '4.25', 'payer' => 'FG', 'payee' => 'PARTNER', 'note' => 'Illustrative',
        ])->assertRedirect();

        $rule = RuleCombination::latest('id')->firstOrFail();
        $this->assertSame(425, $rule->activeVersion->rate_bps);

        $this->post("/rules/{$rule->id}/versions", ['kind' => 'case_by_case'])->assertRedirect("/rules/{$rule->id}");
        $this->get("/rules/{$rule->id}")->assertOk()->assertSee('v2')->assertSee('4.25%');

        $this->post("/rules/{$rule->id}/deactivate")->assertRedirect("/rules/{$rule->id}");
        $this->assertFalse($rule->fresh()->isActive());
        $this->assertSame(2, $rule->versions()->count());
    }

    public function test_rule_screen_rejects_duplicates_and_inconsistent_fields(): void
    {
        $this->from('/rules/create')->post('/rules', [
            'segment' => 'BUILDING_ENVELOPE', 'region' => 'GCC', 'lead_company' => 'PARTNER', 'sale_type' => 'GLASS_ONLY',
            'kind' => 'fixed', 'rate' => '7', 'payer' => 'FG', 'payee' => 'PARTNER',
        ])->assertRedirect('/rules/create')->assertSessionHasErrors('business_rule');

        $this->from('/rules/create')->post('/rules', [
            'segment' => 'PROTECTION', 'region' => 'INDIA', 'lead_company' => 'FG', 'sale_type' => 'GLASS_ONLY',
            'kind' => 'case_by_case', 'rate' => '2',
        ])->assertSessionHasErrors('rate');

        $this->from('/rules/create')->post('/rules', [
            'segment' => 'PROTECTION', 'region' => 'INDIA', 'lead_company' => 'FG', 'sale_type' => 'GLASS_ONLY',
            'kind' => 'fixed', 'rate' => '2', 'payer' => 'FG', 'payee' => 'FG',
        ])->assertSessionHasErrors('payee');

        $this->from('/rules/create')->post('/rules', [
            'segment' => 'PROTECTION', 'region' => 'INDIA', 'lead_company' => 'FG', 'sale_type' => 'GLASS_ONLY',
            'kind' => 'fixed', 'payer' => 'FG', 'payee' => 'PARTNER',
        ])->assertSessionHasErrors('rate');

        $this->assertSame(4, RuleCombination::count());
    }

    public function test_history_and_detail_screens_record_a_manual_decision(): void
    {
        $this->post('/calculate', ['segment' => 'SPECIALTY', 'region' => 'INDIA', 'sale_type' => 'COMPLETE_SYSTEM'] + self::FIXED);
        $calculation = Calculation::latest('id')->firstOrFail();

        $this->get('/calculations')->assertOk()->assertSee('Specialty');
        $this->get("/calculations/{$calculation->uuid}")->assertOk()
            ->assertSee('Original outcome')
            ->assertSee('Record final decision');

        $this->from("/calculations/{$calculation->uuid}")
            ->post("/calculations/{$calculation->uuid}/decision", [
                'rate' => '2.5', 'payer' => 'FG', 'payee' => 'FG', 'approver_name' => 'Demo Approver', 'reason' => '',
            ])
            ->assertSessionHasErrors(['payee', 'reason']);

        $this->from("/calculations/{$calculation->uuid}")
            ->post("/calculations/{$calculation->uuid}/decision", [
                'rate' => '100.5', 'payer' => 'FG', 'payee' => 'PARTNER', 'approver_name' => 'Demo Approver', 'reason' => 'Demo',
            ])
            ->assertSessionHasErrors('rate');

        $this->assertSame(0, ManualDecision::count());

        $this->followingRedirects()
            ->post("/calculations/{$calculation->uuid}/decision", [
                'rate' => '2.5', 'payer' => 'FG', 'payee' => 'PARTNER', 'approver_name' => 'Demo Approver', 'reason' => 'Illustrative reason',
            ])
            ->assertOk()
            ->assertSee('Manual decision recorded')
            ->assertSee('AED 2,500.00')
            ->assertSee('Demo Approver')
            ->assertSee('self-declared')
            ->assertSee('Decided case by case')
            ->assertDontSee('Record final decision');

        $this->from("/calculations/{$calculation->uuid}")
            ->post("/calculations/{$calculation->uuid}/decision", [
                'rate' => '3', 'payer' => 'FG', 'payee' => 'PARTNER', 'approver_name' => 'Someone else', 'reason' => 'Second',
            ])
            ->assertSessionHasErrors('business_rule');

        $this->assertSame(1, ManualDecision::count());
    }

    public function test_fixed_calculation_detail_does_not_offer_a_manual_decision(): void
    {
        $this->post('/calculate', self::FIXED);
        $calculation = Calculation::latest('id')->firstOrFail();

        $this->get("/calculations/{$calculation->uuid}")->assertOk()
            ->assertSee('Not applicable')
            ->assertDontSee('Record final decision');
    }
}
