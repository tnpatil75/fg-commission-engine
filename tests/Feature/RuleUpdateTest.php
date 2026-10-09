<?php

namespace Tests\Feature;

use App\Commission\BusinessRuleViolation;
use App\Commission\Data\RuleVersionInput;
use App\Commission\RuleKind;
use App\Commission\RuleManagementService;
use App\Models\AuditRecord;
use App\Models\Calculation;
use App\Models\Company;
use App\Models\ProductSegment;
use App\Models\Region;
use App\Models\RuleCombination;
use App\Models\RuleVersion;
use App\Models\SaleType;
use Database\Seeders\IllustrativeRuleSeeder;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleUpdateTest extends TestCase
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

    private function fixedRule(): RuleCombination
    {
        return $this->combination('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY');
    }

    /** @param  array<string, string>  $overrides */
    private function fixedPayload(array $overrides = []): array
    {
        return $overrides + [
            'kind' => 'fixed',
            'rate' => '6.50',
            'payer' => 'PARTNER',
            'payee' => 'FG',
            'note' => 'Revised illustrative rate',
        ];
    }

    /** @return array<string, string> */
    private function jsonHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    public function test_rules_screen_shows_current_terms_and_an_edit_link(): void
    {
        $rule = $this->fixedRule();

        $this->get('/rules')->assertOk()
            ->assertSee('Edit / Update')
            ->assertSee('Fixed percentage')
            ->assertSee('Decided case by case')
            ->assertSee('5.00%')
            ->assertSee('0.00%')
            ->assertSee('Active')
            ->assertSeeInOrder(['5.00%', 'FG', 'Partner'])
            ->assertSee(route('rules.show', $rule).'#version-form', false);
    }

    public function test_update_form_prefills_current_values_and_keeps_the_combination_read_only(): void
    {
        $fixed = $this->document($this->get('/rules/'.$this->fixedRule()->id));

        $this->assertNotNull($fixed->querySelector('#version-kind-fixed[checked]'));
        $this->assertSame('5.00', $fixed->getElementById('version-rate')->getAttribute('value'));
        $this->assertSame('FG', $fixed->querySelector('#version-payer option[selected]')?->getAttribute('value'));
        $this->assertSame('PARTNER', $fixed->querySelector('#version-payee option[selected]')?->getAttribute('value'));
        $this->assertSame(IllustrativeRuleSeeder::NOTE, $fixed->getElementById('version-note')->getAttribute('value'));
        $this->assertSame('Save updated rule', trim($fixed->querySelector('#version-form button[type="submit"]')->textContent));
        $this->assertStringContainsString('new version', $fixed->getElementById('version-save-note')->textContent);
        $this->assertCombinationIsReadOnly($fixed, ['Building Envelope', 'GCC', 'Partner', 'Glass only']);

        $case = $this->document($this->get('/rules/'.$this->combination('SPECIALTY', 'INDIA', 'PARTNER', 'COMPLETE_SYSTEM')->id));
        $this->assertNotNull($case->querySelector('#version-kind-case_by_case[checked]'));
        $this->assertSame('', $case->getElementById('version-rate')->getAttribute('value'));
        $this->assertNull($case->querySelector('#version-payer option[selected]'));
        $this->assertNull($case->querySelector('#version-payee option[selected]'));

        $zero = $this->document($this->get('/rules/'.$this->combination('BUILDING_ENVELOPE', 'INDIA', 'FG', 'GLASS_ONLY')->id));
        $this->assertSame('0.00', $zero->getElementById('version-rate')->getAttribute('value'));
    }

    public function test_successful_update_creates_a_new_version_and_leaves_the_previous_one_unchanged(): void
    {
        $rule = $this->fixedRule();
        $previous = $rule->activeVersion;
        $previousCreatedAt = $previous->created_at->toIso8601String();

        $this->post("/rules/{$rule->id}/versions", $this->fixedPayload())
            ->assertRedirect(route('rules.show', $rule))
            ->assertSessionHasNoErrors();

        $this->get(route('rules.show', $rule))->assertOk()
            ->assertSee('Rule #'.$rule->id.' v2 created and now active. Earlier versions are kept unchanged.')
            ->assertSee('6.50%')
            ->assertSee('5.00%');

        $rule->refresh();
        $previous->refresh();

        $this->assertSame(2, $rule->versions()->count());
        $this->assertSame(2, $rule->activeVersion->version);
        $this->assertSame(650, $rule->activeVersion->rate_bps);
        $this->assertSame('PARTNER', $rule->activeVersion->payer->code);
        $this->assertSame('FG', $rule->activeVersion->payee->code);
        $this->assertNotSame($previous->id, $rule->active_rule_version_id);
        $this->assertSame(500, $previous->rate_bps);
        $this->assertSame(IllustrativeRuleSeeder::NOTE, $previous->note);
        $this->assertSame($previousCreatedAt, $previous->created_at->toIso8601String());
        $this->assertSame(4, RuleCombination::count());
    }

    public function test_previous_calculation_and_audit_snapshot_stay_unchanged_after_an_update(): void
    {
        $original = $this->calculate('BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY', '1000.00', 'AED');
        $rule = $this->fixedRule();
        $versionId = $rule->active_rule_version_id;
        $calculation = Calculation::where('uuid', $original->calculationId)->firstOrFail();
        $audit = $calculation->originalAudit;
        $auditCount = AuditRecord::count();

        $this->post("/rules/{$rule->id}/versions", $this->fixedPayload())->assertRedirect();

        $calculation->refresh();
        $audit->refresh();

        $this->assertSame($versionId, $calculation->rule_version_id);
        $this->assertSame(500, $calculation->rate_bps);
        $this->assertSame(5_000, $calculation->commission_minor);
        $this->assertSame('5.00', $audit->rule_snapshot['rate_percent']);
        $this->assertSame(1, $audit->rule_snapshot['version']);
        $this->assertSame($original->explanation, $audit->explanation);
        $this->assertSame('Partner', $audit->result_snapshot['payee']['name']);
        $this->assertSame($auditCount, AuditRecord::count());
    }

    public function test_invalid_fixed_rule_inputs_and_identical_payer_payee_are_rejected(): void
    {
        $rule = $this->fixedRule();
        $show = "/rules/{$rule->id}";
        $url = "{$show}/versions";

        $this->from($show)->post($url, $this->fixedPayload(['rate' => '']))
            ->assertSessionHasErrors(['rate' => 'A fixed rule needs a rate (use 0 for an explicit zero rate).']);

        $this->from($show)->post($url, $this->fixedPayload(['payer' => '', 'payee' => '']))
            ->assertSessionHasErrors([
                'payer' => 'A fixed rule needs a payer.',
                'payee' => 'A fixed rule needs a payee.',
            ]);

        $this->from($show)->post($url, $this->fixedPayload(['payer' => 'FG', 'payee' => 'FG']))
            ->assertSessionHasErrors(['payee' => 'The payer and payee must be different companies.']);

        $this->from($show)->post($url, $this->fixedPayload(['rate' => '1.005']))
            ->assertSessionHasErrors(['rate' => 'The rate must have at most 2 decimal places.']);

        $this->from($show)->post($url, $this->fixedPayload(['rate' => '100.01']))
            ->assertSessionHasErrors(['rate' => 'The rate must be between 0% and 100%.']);

        $document = $this->document($this->followingRedirects()->from($show)->post($url, $this->fixedPayload([
            'rate' => '1.005',
            'note' => 'kept note',
        ])));
        $this->assertSame('1.005', $document->getElementById('version-rate')->getAttribute('value'));
        $this->assertSame('kept note', $document->getElementById('version-note')->getAttribute('value'));
        $this->assertSame('The rate must have at most 2 decimal places.', $document->getElementById('version-rate-error')->textContent);
        $this->assertStringContainsString('version-rate-error', $document->getElementById('version-rate')->getAttribute('aria-describedby'));

        $this->assertSame(1, $rule->versions()->count());
        $this->assertSame(500, $rule->fresh()->activeVersion->rate_bps);
    }

    public function test_an_explicit_zero_percent_update_is_stored(): void
    {
        $rule = $this->fixedRule();

        $this->post("/rules/{$rule->id}/versions", $this->fixedPayload(['rate' => '0', 'payer' => 'FG', 'payee' => 'PARTNER']))
            ->assertRedirect(route('rules.show', $rule))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $rule->fresh()->activeVersion->rate_bps);
        $this->assertSame(500, $rule->versions()->where('version', 1)->firstOrFail()->rate_bps);
    }

    public function test_case_by_case_update_rejects_prohibited_fixed_fields(): void
    {
        $rule = $this->fixedRule();
        $show = "/rules/{$rule->id}";
        $url = "{$show}/versions";
        $message = 'A case-by-case rule must not set a rate, payer or payee.';

        $this->from($show)->post($url, [
            'kind' => 'case_by_case',
            'rate' => '2',
            'payer' => 'FG',
            'payee' => 'PARTNER',
        ])->assertSessionHasErrors(['rate' => $message, 'payer' => $message, 'payee' => $message]);

        $this->assertSame(1, $rule->versions()->count());

        $this->post($url, ['kind' => 'case_by_case', 'note' => 'Now decided manually'])->assertRedirect();

        $created = $rule->fresh()->activeVersion;
        $this->assertSame(RuleKind::CaseByCase, $created->kind);
        $this->assertNull($created->rate_bps);
        $this->assertNull($created->payer_company_id);
        $this->assertNull($created->payee_company_id);
        $this->assertSame(500, $rule->versions()->where('version', 1)->firstOrFail()->rate_bps);
    }

    public function test_ajax_update_returns_json_and_validation_errors_stay_json(): void
    {
        $rule = $this->fixedRule();
        $url = "/rules/{$rule->id}/versions";

        $this->withHeaders($this->jsonHeaders())
            ->post($url, $this->fixedPayload(['rate' => '', 'payer' => 'FG', 'payee' => 'FG']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'rate' => 'A fixed rule needs a rate (use 0 for an explicit zero rate).',
                'payee' => 'The payer and payee must be different companies.',
            ]);

        $this->withHeaders($this->jsonHeaders())
            ->post($url, ['kind' => 'case_by_case', 'rate' => '2', 'payer' => 'FG', 'payee' => 'PARTNER'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rate', 'payer', 'payee']);

        $this->assertSame(1, $rule->versions()->count());

        $created = $this->withHeaders($this->jsonHeaders())
            ->post($url, $this->fixedPayload())
            ->assertOk()
            ->assertJsonPath('redirect', route('rules.show', $rule))
            ->assertJsonPath('message', 'Rule #'.$rule->id.' v2 created and now active. Earlier versions are kept unchanged.');

        $created->assertSessionHas('status');
        $this->assertSame(650, $rule->fresh()->activeVersion->rate_bps);
        $this->assertSame(500, RuleVersion::query()->findOrFail($rule->versions()->where('version', 1)->value('id'))->rate_bps);
    }

    public function test_an_update_preserves_one_active_rule_for_the_combination(): void
    {
        $rule = $this->fixedRule();
        $dimensions = $rule->only(['product_segment_id', 'region_id', 'lead_company_id', 'sale_type_id']);

        $this->post("/rules/{$rule->id}/versions", $this->fixedPayload([
            'segment' => 'SPECIALTY',
            'region' => 'INDIA',
            'lead_company' => 'FG',
            'sale_type' => 'COMPLETE_SYSTEM',
        ]))->assertRedirect();

        $rule->refresh();
        $this->assertSame($dimensions, $rule->only(['product_segment_id', 'region_id', 'lead_company_id', 'sale_type_id']));
        $this->assertSame(4, RuleCombination::count());
        $this->assertNotNull($rule->active_rule_version_id);
        $this->assertSame(2, $rule->versions()->count());

        try {
            $this->rules()->createRule(
                'BUILDING_ENVELOPE', 'GCC', 'PARTNER', 'GLASS_ONLY', RuleVersionInput::fixed(700, 'FG', 'PARTNER'),
            );
            $this->fail('A second active rule was created for the same combination.');
        } catch (BusinessRuleViolation $e) {
            $this->assertStringContainsString('An active rule already exists', $e->getMessage());
        }

        $this->assertSame(4, RuleCombination::count());
        $this->assertSame(2, $rule->versions()->count());
    }

    public function test_an_inactive_rule_keeps_an_explicit_reactivation_flow(): void
    {
        $rule = $this->fixedRule();
        $this->post("/rules/{$rule->id}/deactivate")->assertRedirect();

        $document = $this->document($this->get("/rules/{$rule->id}"));
        $form = $document->getElementById('version-form');

        $this->assertStringContainsString('Reactivate with a new version', $form->textContent);
        $this->assertStringContainsString('This rule is inactive', $form->textContent);
        $this->assertSame('Reactivate rule', trim($form->querySelector('button[type="submit"]')->textContent));
        $this->assertStringNotContainsString('Save updated rule', $form->textContent);
        $this->assertSame('5.00', $document->getElementById('version-rate')->getAttribute('value'));
        $this->get('/rules')->assertOk()->assertSee('No active version')->assertSee('Inactive')->assertSee('Edit / Update');

        $this->post("/rules/{$rule->id}/versions", $this->fixedPayload(['rate' => '4', 'payer' => 'FG', 'payee' => 'PARTNER']))
            ->assertRedirect(route('rules.show', $rule));

        $rule->refresh();
        $this->assertTrue($rule->isActive());
        $this->assertNull($rule->deactivated_at);
        $this->assertSame(400, $rule->activeVersion->rate_bps);
        $this->assertSame(500, $rule->versions()->where('version', 1)->firstOrFail()->rate_bps);
    }

    /** @param  list<string>  $expected */
    private function assertCombinationIsReadOnly(HTMLDocument $document, array $expected): void
    {
        $combination = $document->getElementById('rule-combination');
        $this->assertNotNull($combination);
        foreach ($expected as $text) {
            $this->assertStringContainsString($text, $combination->textContent);
        }
        $this->assertSame(0, $document->querySelectorAll(
            '#version-form [name="segment"], #version-form [name="region"], #version-form [name="lead_company"], #version-form [name="sale_type"]'
        )->length);
        $this->assertNotNull($document->querySelector('#version-form a[href$="/rules/create"]'));
    }

    private function document(\Illuminate\Testing\TestResponse $response): HTMLDocument
    {
        return HTMLDocument::createFromString($response->assertOk()->getContent(), LIBXML_NOERROR);
    }
}
