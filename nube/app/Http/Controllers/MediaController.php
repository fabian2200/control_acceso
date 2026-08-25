<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            abort(404);
        }

        if (! preg_match('#^acceso/[A-Za-z0-9._/-]+\.(jpg|jpeg|png|webp)$#i', $path)) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path);
    }
}
