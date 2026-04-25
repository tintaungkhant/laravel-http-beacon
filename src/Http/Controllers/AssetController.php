<?php

namespace HttpBeacon\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController extends Controller
{
    public function serve(string $path): BinaryFileResponse|Response
    {
        $base = realpath(dirname(__DIR__, 3).'/public/build');
        $resolved = realpath($base.DIRECTORY_SEPARATOR.$path);

        if (! $base || ! $resolved || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR)) {
            return response('Not found', 404);
        }

        return response()->file($resolved, [
            'Content-Type' => $this->mimeFor($resolved),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function mimeFor(string $file): string
    {
        return match (true) {
            str_ends_with($file, '.css') => 'text/css',
            str_ends_with($file, '.js') => 'application/javascript',
            str_ends_with($file, '.map') => 'application/json',
            default => 'application/octet-stream',
        };
    }
}
