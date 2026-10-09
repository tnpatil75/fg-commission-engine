@extends('layouts.app')

@section('title', 'New rule')

@section('content')
    <h1>New rule</h1>

    <form method="post" action="{{ route('rules.store') }}" class="card" id="rule-form" novalidate data-validate>
        @csrf
        <div class="grid-2">
            <div>
                <h2>Exact combination</h2>
                @include('partials.select', ['form' => 'rule', 'name' => 'segment', 'label' => 'Product segment', 'options' => $masters['segments'],
                    'validate' => ['required' => true, 'label' => 'product segment']])
                @include('partials.select', ['form' => 'rule', 'name' => 'region', 'label' => 'Sale destination region', 'options' => $masters['regions'],
                    'validate' => ['required' => true, 'label' => 'sale destination region']])
                @include('partials.select', ['form' => 'rule', 'name' => 'lead_company', 'label' => 'Lead-generating company', 'options' => $masters['companies'],
                    'validate' => ['required' => true, 'label' => 'lead-generating company']])
                @include('partials.select', ['form' => 'rule', 'name' => 'sale_type', 'label' => 'Sale type', 'options' => $masters['sale_types'],
                    'validate' => ['required' => true, 'label' => 'sale type']])
                <p class="hint">Only one active rule is allowed per combination. If the combination already has an inactive
                    rule, this creates its next version and reactivates it.</p>
            </div>
            <div>
                <h2>Version 1 content</h2>
                @include('rules._version_fields', ['form' => 'rule', 'masters' => $masters])
            </div>
        </div>
        <button type="submit">Create rule</button>
        <a href="{{ route('rules.index') }}">Cancel</a>
    </form>
@endsection
