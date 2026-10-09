@extends('layouts.app')

@section('title', 'Rule #'.$rule->id)

@section('content')
    <h1>
        Rule #{{ $rule->id }}
        <span class="badge {{ $rule->isActive() ? 'active' : 'inactive' }}">{{ $rule->isActive() ? 'Active' : 'Inactive' }}</span>
    </h1>
    <p><strong>{{ $rule->label() }}</strong></p>

    <section class="card table-card">
        <table>
            <thead>
            <tr>
                <th>Version</th>
                <th>Type</th>
                <th class="num">Rate</th>
                <th>Payment direction</th>
                <th>Note</th>
                <th>Created (UTC)</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rule->versions as $version)
                <tr>
                    <td>v{{ $version->version }}</td>
                    <td>{{ $version->kind->label() }}</td>
                    <td class="num">{{ $version->rate_bps === null ? '—' : \App\Commission\Money\MoneyCalculator::rateToPercent($version->rate_bps).'%' }}</td>
                    <td>{{ $version->payer ? $version->payer->name.' pays '.$version->payee->name : '—' }}</td>
                    <td>{{ $version->note ?? '—' }}</td>
                    <td>{{ $version->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>
                        @if ($rule->active_rule_version_id === $version->id)
                            <span class="badge active">Active</span>
                        @else
                            <span class="badge inactive">Historical</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    <p class="hint">Versions are immutable. Calculations keep a snapshot of the version they used.</p>

    <div class="grid-2">
        <form method="post" action="{{ route('rules.versions.store', $rule) }}" class="card" id="version-form" novalidate data-validate data-submit="ajax">
            @csrf
            @if ($rule->isActive())
                <h2>Edit / Update</h2>
                <p class="hint" id="version-save-note">Saving creates a new version and keeps it active. Previous versions, calculations and audit records stay unchanged.</p>
            @else
                <h2>Reactivate with a new version</h2>
                <p class="hint" id="version-save-note">This rule is inactive. This form is the reactivation flow: saving creates a new version and makes the rule active again. Previous versions, calculations and audit records stay unchanged.</p>
            @endif

            <h3>Combination</h3>
            <dl class="facts" id="rule-combination">
                <dt>Product segment</dt>
                <dd>{{ $rule->segment->name }}</dd>
                <dt>Region</dt>
                <dd>{{ $rule->region->name }}</dd>
                <dt>Lead company</dt>
                <dd>{{ $rule->leadCompany->name }}</dd>
                <dt>Sale type</dt>
                <dd>{{ $rule->saleType->name }}</dd>
            </dl>
            <p class="hint">These four values define this rule. A different combination is created with <a href="{{ route('rules.create') }}">New rule</a>.</p>

            @include('rules._version_fields', [
                'form' => 'version',
                'masters' => $masters,
                'values' => ($rule->activeVersion ?? $rule->versions->first())?->formValues() ?? [],
            ])
            @if ($rule->isActive())
                <button type="submit" aria-describedby="version-save-note">Save updated rule</button>
            @else
                <button type="submit" aria-describedby="version-save-note">Reactivate rule</button>
            @endif
        </form>

        <section class="card">
            <h2>Deactivate</h2>
            @if ($rule->isActive())
                <p>After deactivation this combination returns "No matching rule — manual review required".
                    Earlier calculations and versions are not changed.</p>
                <form method="post" action="{{ route('rules.deactivate', $rule) }}"
                      onsubmit="return confirm('Deactivate rule #{{ $rule->id }}?');">
                    @csrf
                    <button type="submit" class="secondary">Deactivate rule</button>
                </form>
            @else
                <p class="muted">Inactive since {{ $rule->deactivated_at?->format('Y-m-d H:i:s') }} UTC. Create a new version to reactivate it.</p>
            @endif
        </section>
    </div>
@endsection
