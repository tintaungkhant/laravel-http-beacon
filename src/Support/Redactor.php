<?php

namespace HttpBeacon\Support;

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

        foreach ($hidden as $path) {
            self::maskPath($data, explode('.', $path));
        }

        return $data;
    }

    private static function maskPath(array &$data, array $segments): void
    {
        if (empty($segments)) {
            return;
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            foreach ($data as $key => $value) {
                if (empty($segments)) {
                    $data[$key] = self::MASK;
                } elseif (is_array($value)) {
                    self::maskPath($data[$key], $segments);
                }
            }

            return;
        }

        if (! array_key_exists($segment, $data)) {
            return;
        }

        if (empty($segments)) {
            $data[$segment] = self::MASK;

            return;
        }

        if (is_array($data[$segment])) {
            self::maskPath($data[$segment], $segments);
        }
    }
}
