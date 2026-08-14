<?php

namespace App\Support;

class KioskoState
{
    public const KEY = 'kiosko';

    public static function all(): array
    {
        return session(self::KEY, []);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return data_get(self::all(), $key, $default);
    }

    public static function put(array $data): void
    {
        session([self::KEY => array_merge(self::all(), $data)]);
    }

    public static function empleado(): ?array
    {
        return self::get('empleado');
    }

    public static function userId(): ?int
    {
        $id = self::get('empleado.user_id');

        return $id !== null ? (int) $id : null;
    }

    public static function empleadoVista(): ?array
    {
        $empleado = self::empleado();

        if (! $empleado) {
            return null;
        }

        $modelo = \App\Models\Empleado::query()->with('cargoRel')->find($empleado['id']);

        if ($modelo) {
            $empleado['foto'] = $modelo->fotoSrc();
        }

        return $empleado;
    }

    public static function forget(string ...$keys): void
    {
        $state = self::all();
        foreach ($keys as $key) {
            unset($state[$key]);
        }
        session([self::KEY => $state]);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }
}
