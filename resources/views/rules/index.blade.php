@extends('layouts.app')

@section('title', 'Rules')

@section('content')
    <div class="actions">
        <h1>Rules</h1>
        <a class="button" href="{{ route('rules.create') }}">New rule</a>
    </div>
    <p class="muted">Each rule matches one exact combination of segment, sale destination, lead company and sale type.
        Combinations without an active rule return "No matching rule — manual review required".
        Edit / Update saves a new version of an active rule and leaves earlier calculations unchanged.</p>

    <div class="card table-card">
        <table>
            <thead>
            <tr>
                <th>Rule</th>
                <th>Segment / region / lead / sale type</th>
                <th>Status</th>
                <th>Type</th>
                <th class="num">Percentage</th>
                <th>Payer</th>
                <th>Payee</th>
                <th class="num">Versions</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rules as $rule)
                @php($version = $rule->activeVersion)
                <tr>
                    <td>#{{ $rule->id }}</td>
                    <td>{{ $rule->label() }}</td>
                    <td>
                        <span class="badge {{ $rule->isActive() ? 'active' : 'inactive' }}">{{ $rule->isActive() ? 'Active' : 'Inactive' }}</span>
                    </td>
                    @if ($version)
                        <td>
                            {{ $version->kind->label() }}
                            <div class="muted">Version {{ $version->version }}</div>
                        </td>
                        <td class="num">{{ $version->rate_bps === null ? '—' : \App\Commission\Money\MoneyCalculator::rateToPercent($version->rate_bps).'%' }}</td>
                        <td>{{ $version->payer?->name ?? '—' }}</td>
                        <td>{{ $version->payee?->name ?? '—' }}</td>
                    @else
                        <td class="muted">No active version</td>
                        <td class="num muted">—</td>
                        <td class="muted">—</td>
                        <td class="muted">—</td>
                    @endif
                    <td class="num">{{ $rule->versions_count }}</td>
                    <td class="row-actions">
                        <a href="{{ route('rules.show', $rule) }}">Versions</a>
                        <a class="button" href="{{ route('rules.show', $rule) }}#version-form">Edit / Update<span class="visually-hidden"> rule #{{ $rule->id }}</span></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">No rules configured.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
