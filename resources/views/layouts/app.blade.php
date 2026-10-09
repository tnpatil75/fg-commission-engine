<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Commission rules') · FG commission demo</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/form-validation.js') }}" defer></script>
</head>
<body>
<header class="site-header">
    <div class="wrap">
        <strong class="brand">FG commission &amp; referral rules <span class="tag">Demo</span></strong>
        <nav>
            <a href="{{ route('calculate.create') }}" @class(['active' => request()->routeIs('calculate.*')])>Calculate</a>
            <a href="{{ route('rules.index') }}" @class(['active' => request()->routeIs('rules.*')])>Rules</a>
            <a href="{{ route('calculations.index') }}" @class(['active' => request()->routeIs('calculations.*')])>History</a>
        </nav>
    </div>
</header>

<div class="wrap">
    <p class="notice">
        Illustrative sample data: rates and payment directions are invented and are not FG's commercial terms.
        A calculation does not confirm that a payment is due or has been paid.
    </p>
</div>

<main class="wrap">
    @if (session('status'))
        <div class="flash" role="status">{{ session('status') }}</div>
    @endif
    @error('business_rule')
        <div class="alert" role="alert">{{ $message }}</div>
    @enderror

    @yield('content')
</main>
</body>
</html>
