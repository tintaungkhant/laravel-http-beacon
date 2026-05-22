<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iNyIgZmlsbD0iIzRmNDZlNSIvPjx0ZXh0IHg9IjE2IiB5PSIyMyIgZm9udC1mYW1pbHk9IkFyaWFsLEhlbHZldGljYSxzYW5zLXNlcmlmIiBmb250LXNpemU9IjIxIiBmb250LXdlaWdodD0iYm9sZCIgZmlsbD0iI2ZmZiIgdGV4dC1hbmNob3I9Im1pZGRsZSI+QjwvdGV4dD48L3N2Zz4=">

    <title>Beacon{{ config('app.name') ? ' — ' . config('app.name') : '' }}</title>

    @php($beaconAssetVersion = \HttpBeacon\Beacon::assetVersion())

    <link rel="stylesheet" href="{{ url('beacon/assets/app.css') }}?v={{ $beaconAssetVersion }}">
</head>
<body>
    <div id="beacon"></div>

    <script type="module" src="{{ url('beacon/assets/app.js') }}?v={{ $beaconAssetVersion }}"></script>
</body>
</html>
