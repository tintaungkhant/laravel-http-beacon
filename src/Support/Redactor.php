<?php

namespace HttpBeacon\Support;

use Illuminate\Support\Arr;

class Redactor
{
    private const MASK = '********';

    public static function headers(array $headers): array
    {
        $hidden = array_map('strtolower', (array) config('beacon.hidden_headers', []));
        $result = [];

        foreach ($headers as $name => $values) {
            $lcName = strtolower($name);
            $value = is_array($values) ? implode(', ', $values) : (string) $values;
            $result[$lcName] = in_array($lcName, $hidden, true) ? self::MASK : $value;
        }

        return $result;
    }

    public static function parameters(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $hidden = (array) config('beacon.hidden_parameters', []);

        foreach ($hidden as $key) {
            if (Arr::has($data, $key)) {
                Arr::set($data, $key, self::MASK);
            }
        }

        return $data;
    }
}
