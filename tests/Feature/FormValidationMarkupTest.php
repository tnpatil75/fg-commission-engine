<?php

namespace Tests\Feature;

use App\Commission\Money\Currency;
use App\Commission\Money\MoneyCalculator;
use App\Http\Requests\CalculateCommissionRequest;
use App\Http\Requests\RecordManualDecisionRequest;
use App\Http\Requests\StoreRuleRequest;
use App\Http\Requests\StoreRuleVersionRequest;
use App\Models\Calculation;
use App\Models\RuleCombination;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Markup that public/js/form-validation.js relies on. The behaviour itself is verified in a browser. */
class FormValidationMarkupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const PENDING = [
        'segment' => 'SPECIALTY', 'region' => 'INDIA', 'lead_company' => 'PARTNER',
        'sale_type' => 'COMPLETE_SYSTEM', 'amount' => '750000.00', 'currency' => 'INR',
    ];

    public function test_editable_forms_opt_in_with_unique_ids_and_connected_labels(): void
    {
        foreach ($this->editableForms() as $url => $formId) {
            $document = $this->document($this->get($url));
            $form = $document->getElementById($formId);

            $this->assertNotNull($form, "{$url} has #{$formId}");
            $this->assertTrue($form->hasAttribute('data-validate'), "{$formId} opts in");
            $this->assertTrue($form->hasAttribute('novalidate'), "{$formId} keeps novalidate");
            $this->assertNotNull($document->querySelector('script[src$="/js/form-validation.js"][defer]'));

            $ids = array_map(fn (Element $e) => $e->id, iterator_to_array($document->querySelectorAll('[id]')));
            $this->assertSame(array_unique($ids), $ids, "{$url} has no duplicate IDs");

            foreach ($form->querySelectorAll('[data-field]') as $wrapper) {
                $name = $wrapper->getAttribute('data-field');
                $controls = $wrapper->querySelectorAll('[name="'.$name.'"]');
                $this->assertGreaterThan(0, $controls->length, "{$formId} {$name} has a control");

                foreach ($controls as $control) {
                    $this->assertStringStartsWith(explode('-', $formId)[0].'-'.$name, $control->id);
                    $label = $control->getAttribute('type') === 'radio'
                        ? $control->parentElement
                        : $form->querySelector('label[for="'.$control->id.'"]');
                    $this->assertSame('label', strtolower($label?->tagName ?? ''), "{$control->id} has a label");
                }
            }

            foreach ($form->querySelectorAll('[aria-describedby]') as $control) {
                foreach (explode(' ', $control->getAttribute('aria-describedby')) as $id) {
                    $this->assertNotNull($document->getElementById($id), "{$control->id} describedby #{$id} exists");
                }
            }
        }
    }

    public function test_text_length_limits_match_the_form_requests(): void
    {
        $calculate = $this->document($this->get('/calculate'));
        $this->assertMaxLength($calculate, 'calculate-form', CalculateCommissionRequest::class, ['external_order_reference', 'plant_reference']);

        $create = $this->document($this->get('/rules/create'));
        $this->assertMaxLength($create, 'rule-form', StoreRuleRequest::class, ['note']);

        $show = $this->document($this->get('/rules/'.RuleCombination::firstOrFail()->id));
        $this->assertMaxLength($show, 'version-form', StoreRuleVersionRequest::class, ['note']);

        $detail = $this->document($this->get('/calculations/'.$this->pendingCalculation()->uuid));
        $this->assertMaxLength($detail, 'decision-form', RecordManualDecisionRequest::class, ['approver_name', 'reason']);
    }

    public function test_money_limits_come_from_backend_constants(): void
    {
        $amount = $this->document($this->get('/calculate'))->querySelector('#calculate-form [data-field="amount"]');
        $this->assertSame('amount', $amount->getAttribute('data-format'));
        $this->assertSame((string) MoneyCalculator::MAX_AMOUNT_MINOR, $amount->getAttribute('data-max-minor'));
        $this->assertSame(MoneyCalculator::MAX_AMOUNT_DISPLAY, $amount->getAttribute('data-max-display'));
        $this->assertSame((string) Currency::USD->minorUnits(), $amount->getAttribute('data-decimals'));

        $rate = $this->document($this->get('/calculations/'.$this->pendingCalculation()->uuid))
            ->querySelector('#decision-form [data-field="rate"]');
        $this->assertSame('percentage', $rate->getAttribute('data-format'));
        $this->assertSame((string) MoneyCalculator::MAX_RATE_BPS, $rate->getAttribute('data-max-bps'));
    }

    public function test_server_field_errors_are_rendered_once_with_ids_the_client_reuses(): void
    {
        $document = $this->document($this->followingRedirects()->from('/calculate')
            ->post('/calculate', ['amount' => '1.005', 'segment' => 'BUILDING_ENVELOPE', 'region' => 'GCC',
                'lead_company' => 'PARTNER', 'sale_type' => 'GLASS_ONLY', 'currency' => 'AED']));

        $input = $document->getElementById('calculate-amount');
        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame('calculate-amount-hint calculate-amount-error', $input->getAttribute('aria-describedby'));
        $this->assertSame('1.005', $input->getAttribute('value'));
        $this->assertSame(1, $document->querySelectorAll('[data-field="amount"] .error')->length);
        $this->assertSame('The eligible sale amount must have at most 2 decimal places.',
            $document->getElementById('calculate-amount-error')->textContent);
        $this->assertSame(1, $document->querySelectorAll('.error')->length);
        $this->assertNull($document->getElementById('calculate-segment')->getAttribute('aria-invalid'));
    }

    public function test_business_rule_errors_stay_outside_the_fields(): void
    {
        $document = $this->document($this->followingRedirects()->from('/rules/create')->post('/rules', [
            'segment' => 'BUILDING_ENVELOPE', 'region' => 'GCC', 'lead_company' => 'PARTNER', 'sale_type' => 'GLASS_ONLY',
            'kind' => 'fixed', 'rate' => '7', 'payer' => 'FG', 'payee' => 'PARTNER',
        ]));

        $alert = $document->querySelector('main > .alert[role="alert"]');
        $this->assertNotNull($alert);
        $this->assertNull($alert->closest('[data-field]'));
        $this->assertSame(0, $document->querySelectorAll('#rule-form .error, #rule-form [aria-invalid]')->length);
        $this->assertSame('7', $document->getElementById('rule-rate')->getAttribute('value'));
    }

    public function test_rule_version_fields_use_a_fieldset_and_conditional_metadata(): void
    {
        $document = $this->document($this->followingRedirects()->from('/rules/create')->post('/rules', [
            'segment' => 'PROTECTION', 'region' => 'INDIA', 'lead_company' => 'FG', 'sale_type' => 'GLASS_ONLY',
            'kind' => 'case_by_case', 'rate' => '2', 'payer' => 'FG',
        ]));

        $kind = $document->getElementById('rule-kind');
        $this->assertSame('fieldset', strtolower($kind->tagName));
        $this->assertSame('Rule type', $kind->querySelector('legend')->textContent);
        $this->assertNotNull($document->querySelector('#rule-kind-case_by_case[checked]'));

        foreach (['rate', 'payer', 'payee'] as $name) {
            $wrapper = $document->querySelector('#rule-form [data-field="'.$name.'"]');
            $this->assertSame('kind=fixed', $wrapper->getAttribute('data-only-when'));
            $this->assertTrue($wrapper->hasAttribute('data-required'));
            $this->assertFalse($wrapper->hasAttribute('hidden'), "{$name} is visible without JavaScript");
        }
        $this->assertSame('payer', $document->querySelector('#rule-form [data-field="payee"]')->getAttribute('data-different-from'));

        // Laravel still rejects prohibited values; the error is wired to the field.
        $this->assertSame('A case-by-case rule must not set a rate, payer or payee.',
            $document->getElementById('rule-rate-error')->textContent);

        $noJs = $document->querySelector('#rule-form [data-no-js]');
        $this->assertStringContainsString('leave rate, payer and payee empty', $noJs->textContent);
        $this->assertFalse($noJs->hasAttribute('hidden'));
        $this->assertTrue($document->querySelector('#rule-form [data-show-when="kind=case_by_case"]')->hasAttribute('hidden'));
    }

    public function test_decision_errors_are_wired_to_their_fields(): void
    {
        $calculation = $this->pendingCalculation();

        $document = $this->document($this->followingRedirects()->from("/calculations/{$calculation->uuid}")
            ->post("/calculations/{$calculation->uuid}/decision", [
                'rate' => '2.5', 'payer' => 'FG', 'payee' => 'FG', 'approver_name' => 'Demo Approver', 'reason' => '   ',
            ]));

        $this->assertSame('The payer and payee must be different companies.',
            $document->getElementById('decision-payee-error')->textContent);
        $this->assertSame('decision-payee-error', $document->getElementById('decision-payee')->getAttribute('aria-describedby'));
        $this->assertSame('A reason is mandatory for a manual decision.',
            $document->getElementById('decision-reason-error')->textContent);
        $this->assertSame('decision-reason-error', $document->getElementById('decision-reason')->getAttribute('aria-describedby'));
        $this->assertSame('decision-reason', $document->querySelector('label[for="decision-reason"]')?->getAttribute('for'));
    }

    public function test_only_the_rule_update_form_opts_into_ajax_submission(): void
    {
        foreach ($this->editableForms() as $url => $formId) {
            $form = $this->document($this->get($url))->getElementById($formId);

            if ($formId === 'version-form') {
                $this->assertSame('ajax', $form->getAttribute('data-submit'));
                $this->assertSame('post', strtolower($form->getAttribute('method')));
                $this->assertStringEndsWith('/versions', $form->getAttribute('action'));
            } else {
                $this->assertFalse($form->hasAttribute('data-submit'), "{$formId} keeps a normal submission");
            }
        }
    }

    public function test_deactivate_form_is_not_opted_in_and_keeps_its_confirmation(): void
    {
        $rule = RuleCombination::firstOrFail();
        $form = $this->document($this->get("/rules/{$rule->id}"))
            ->querySelector('form[action$="/rules/'.$rule->id.'/deactivate"]');

        $this->assertFalse($form->hasAttribute('data-validate'));
        $this->assertSame("return confirm('Deactivate rule #{$rule->id}?');", $form->getAttribute('onsubmit'));
        $this->assertNotNull($form->querySelector('input[name="_token"]'));
    }

    /** The client mirrors Laravel's TrimStrings/ConvertEmptyStringsToNull middleware rather than its own rules. */
    public function test_server_whitespace_handling_mirrored_by_the_client(): void
    {
        $base = ['segment' => 'BUILDING_ENVELOPE', 'region' => 'GCC', 'lead_company' => 'PARTNER',
            'sale_type' => 'GLASS_ONLY', 'currency' => 'AED'];

        $this->post('/calculate', ['amount' => " 100.00\u{00A0}"] + $base)->assertSessionHasNoErrors();
        $this->assertSame(10000, Calculation::latest('id')->firstOrFail()->eligible_amount_minor);

        $this->from('/calculate')->post('/calculate', ['amount' => '1 000.00'] + $base)
            ->assertSessionHasErrors(['amount' => 'The eligible sale amount must be a plain decimal number such as 1250.50 (no spaces, commas, signs or exponents).']);

        $calculation = $this->pendingCalculation();
        $this->from("/calculations/{$calculation->uuid}")->post("/calculations/{$calculation->uuid}/decision", [
            'rate' => '0', 'payer' => 'FG', 'payee' => 'PARTNER', 'approver_name' => " \t ", 'reason' => str_repeat('é', 1000),
        ])->assertSessionHasErrors(['approver_name' => 'The approver name field is required.'])
            ->assertSessionDoesntHaveErrors(['rate', 'reason']);
    }

    /** @return array<string, string> URL => form ID */
    private function editableForms(): array
    {
        return [
            '/calculate' => 'calculate-form',
            '/rules/create' => 'rule-form',
            '/rules/'.RuleCombination::firstOrFail()->id => 'version-form',
            '/calculations/'.$this->pendingCalculation()->uuid => 'decision-form',
        ];
    }

    private function pendingCalculation(): Calculation
    {
        $this->post('/calculate', self::PENDING)->assertSessionHasNoErrors();

        return Calculation::latest('id')->firstOrFail();
    }

    private function document(TestResponse $response): HTMLDocument
    {
        return HTMLDocument::createFromString($response->assertOk()->getContent(), LIBXML_NOERROR);
    }

    /** @param  class-string<\Illuminate\Foundation\Http\FormRequest>  $request */
    private function assertMaxLength(HTMLDocument $document, string $formId, string $request, array $fields): void
    {
        $rules = (new $request)->rules();

        foreach ($fields as $field) {
            $max = collect($rules[$field])->first(fn ($rule) => is_string($rule) && str_starts_with($rule, 'max:'));
            $this->assertNotNull($max, "{$request} has a max rule for {$field}");
            $this->assertSame(substr($max, 4),
                $document->querySelector('#'.$formId.' [data-field="'.$field.'"]')?->getAttribute('data-maxlength'),
                "{$formId} {$field} limit matches {$request}");
        }
    }
}
