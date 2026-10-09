{{-- Original outcome of a calculation. Pending outcomes never show a rate or payable amount. --}}
<section @class(['card', 'result', 'pending' => $result->isPending(), 'final' => ! $result->isPending()]) data-testid="result">
    <h2>{{ $title ?? 'Result' }}</h2>
    <p class="outcome">{{ $result->outcome->label() }}</p>

    <dl class="facts">
        @if ($result->isPending())
            <dt>Rate and amount</dt>
            <dd>Not set: a manual decision is required.</dd>
            <dt>Payer / payee</dt>
            <dd>Not set</dd>
        @else
            <dt>Rate</dt>
            <dd>{{ $result->rateDisplay() }}</dd>
            <dt>Commission / referral amount</dt>
            <dd>{{ $result->commissionDisplay() }}</dd>
            <dt>Payer</dt>
            <dd>{{ $result->payer['name'] }}</dd>
            <dt>Payee</dt>
            <dd>{{ $result->payee['name'] }}</dd>
        @endif
        <dt>Eligible amount</dt>
        <dd>{{ $result->eligibleAmountDisplay() }}</dd>
        <dt>Rule applied</dt>
        <dd>{{ $result->rule['reference'] ?? 'None (no active exact rule)' }}</dd>
        <dt>Calculated at</dt>
        <dd>{{ $result->calculatedAt->format('Y-m-d H:i:s') }} UTC</dd>
    </dl>

    <p class="explanation">{{ $result->explanation }}</p>

    @if ($showLink ?? false)
        <p><a href="{{ route('calculations.show', $result->calculationId) }}">Open audit detail{{ $result->isPending() ? ' and record a manual decision' : '' }}</a></p>
    @endif
</section>
