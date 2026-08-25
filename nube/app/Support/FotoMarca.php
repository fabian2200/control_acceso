<?php

namespace App\Support;

use Illuminate\Http\Request;

class FotoMarca
{
    public static function src(?string $foto): ?string
    {
        if (! is_string($foto) || $foto === '') {
            return null;
        }

        if (str_starts_with($foto, 'data:image')) {
            return $foto;
        }

        if (str_starts_with($foto, 'http://') || str_starts_with($foto, 'https://') || str_starts_with($foto, '/')) {
            return $foto;
        }

        return url('/media/'.ltrim($foto, '/'));
    }

    public static function absoluta(Request $request, ?string $foto): ?string
    {
        $src = self::src($foto);
        if ($src === null) {
            return null;
        }
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://') || str_starts_with($src, 'data:')) {
            return $src;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').$src;
    }
}
