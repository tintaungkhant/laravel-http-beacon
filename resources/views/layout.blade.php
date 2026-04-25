<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>Beacon{{ config('app.name') ? ' — ' . config('app.name') : '' }}</title>

    <link rel="stylesheet" href="{{ asset('vendor/beacon/build/app.css') }}">
</head>
<body>
    <div id="beacon"></div>

    <script type="module" src="{{ asset('vendor/beacon/build/app.js') }}"></script>
</body>
</html>
