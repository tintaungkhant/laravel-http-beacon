<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>Beacon{{ config('app.name') ? ' — ' . config('app.name') : '' }}</title>

    @php($beaconAssetVersion = \HttpBeacon\Beacon::assetVersion())

    <link rel="stylesheet" href="{{ url('beacon/assets/app.css') }}?v={{ $beaconAssetVersion }}">
</head>
<body>
    <div id="beacon"></div>

    <script type="module" src="{{ url('beacon/assets/app.js') }}?v={{ $beaconAssetVersion }}"></script>
</body>
</html>
