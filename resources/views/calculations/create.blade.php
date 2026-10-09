@extends('layouts.app')

@section('title', 'Calculate')

@use('App\Commission\Money\Currency')
@use('App\Commission\Money\MoneyCalculator')

@section('content')
    <h1>Calculate commission / referral</h1>

    <div class="grid-2">
        <form method="post" action="{{ route('calculate.store') }}" class="card" id="calculate-form" novalidate
              data-validate data-option-message="The selected :attribute is not a known value.">
            @csrf
            <h2>Sale details</h2>

            @include('partials.select', ['form' => 'calculate', 'name' => 'segment', 'label' => 'Product segment', 'options' => $masters['segments'],
                'validate' => ['required' => true, 'label' => 'product segment']])
            @include('partials.select', ['form' => 'calculate', 'name' => 'region', 'label' => 'Sale destination region', 'options' => $masters['regions'],
                'hint' => 'Where the sale is delivered, not where the lead generator is located.',
                'validate' => ['required' => true, 'label' => 'sale destination region']])
            @include('partials.select', ['form' => 'calculate', 'name' => 'lead_company', 'label' => 'Lead-generating company', 'options' => $masters['companies'],
                'validate' => ['required' => true, 'label' => 'lead-generating company']])
            @include('partials.select', ['form' => 'calculate', 'name' => 'sale_type', 'label' => 'Sale type', 'options' => $masters['sale_types'],
                'validate' => ['required' => true, 'label' => 'sale type']])

            <div class="row">
                @include('partials.input', ['form' => 'calculate', 'name' => 'amount', 'label' => 'Eligible sale amount', 'inputmode' => 'decimal',
                    'placeholder' => 'e.g. 100000.00', 'hint' => 'After discounts, excluding taxes. Up to 2 decimal places.',
                    'validate' => ['required' => true, 'label' => 'eligible sale amount', 'format' => 'amount',
                        'decimals' => Currency::USD->minorUnits(), 'max-minor' => MoneyCalculator::MAX_AMOUNT_MINOR,
                        'max-display' => MoneyCalculator::MAX_AMOUNT_DISPLAY]])
                @include('partials.select', ['form' => 'calculate', 'name' => 'currency', 'label' => 'Currency', 'options' => $masters['currencies'],
                    'validate' => ['required' => true, 'option-message' => 'The currency must be one of: '.implode(', ', Currency::codes()).'.']])
            </div>

            @include('partials.input', ['form' => 'calculate', 'name' => 'external_order_reference', 'label' => 'External order reference (optional)',
                'placeholder' => 'e.g. SO-DEMO-0001', 'hint' => 'Free text from another system; not checked against it.',
                'validate' => ['maxlength' => 100]])
            @include('partials.input', ['form' => 'calculate', 'name' => 'plant_reference', 'label' => 'Plant reference (optional)',
                'placeholder' => 'e.g. DXB', 'hint' => 'Context only; it does not affect rule matching.',
                'validate' => ['maxlength' => 50]])

            <button type="submit">Calculate</button>
        </form>

        <div>
            @if ($result)
                @include('calculations._result', ['result' => $result, 'showLink' => true])
            @else
                <section class="card">
                    <h2>Result</h2>
                    <p class="muted">Choose the four matching values and an amount. The result shows the outcome, rate,
                        amount, payer, payee and an explanation of which rule was applied.</p>
                </section>
            @endif
        </div>
    </div>
@endsection
