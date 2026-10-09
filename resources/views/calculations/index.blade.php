@extends('layouts.app')

@section('title', 'Calculation history')

@section('content')
    <h1>Calculation history</h1>

    @if ($calculations->isEmpty())
        <div class="card"><p class="muted">No calculations yet. <a href="{{ route('calculate.create') }}">Calculate one</a>.</p></div>
    @else
        <div class="card table-card">
            <table>
                <thead>
                <tr>
                    <th>Calculated (UTC)</th>
                    <th>Segment / region / lead / sale type</th>
                    <th class="num">Eligible amount</th>
                    <th>Original outcome</th>
                    <th class="num">Amount</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($calculations as $calculation)
                    @php($input = $calculation->originalAudit->input_snapshot)
                    @php($result = \App\Commission\Data\CalculationResult::fromModel($calculation))
                    <tr>
                        <td>{{ $calculation->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            {{ $input['segment']['name'] }} / {{ $input['region']['name'] }} /
                            {{ $input['lead_company']['name'] }} / {{ $input['sale_type']['name'] }}
                            @if ($calculation->external_order_reference)
                                <br><span class="muted">Order {{ $calculation->external_order_reference }}</span>
                            @endif
                        </td>
                        <td class="num">{{ $result->eligibleAmountDisplay() }}</td>
                        <td>{{ $calculation->outcome->label() }}</td>
                        <td class="num">{{ $result->commissionDisplay() ?? '—' }}</td>
                        <td><span class="badge {{ $calculation->status() }}">{{ ucfirst($calculation->status()) }}</span></td>
                        <td><a href="{{ route('calculations.show', $calculation) }}">Detail</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination actions">
            @if ($calculations->previousPageUrl())
                <a href="{{ $calculations->previousPageUrl() }}">« Newer</a>
            @endif
            <span class="muted">Page {{ $calculations->currentPage() }} of {{ $calculations->lastPage() }}</span>
            @if ($calculations->nextPageUrl())
                <a href="{{ $calculations->nextPageUrl() }}">Older »</a>
            @endif
        </div>
    @endif
@endsection
