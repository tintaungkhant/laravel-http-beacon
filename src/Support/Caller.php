<?php

namespace HttpBeacon\Support;

class Caller
{
    public static function find(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $vendorPath = base_path('vendor').DIRECTORY_SEPARATOR;
        $packagePath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR;

        $count = count($trace);

        for ($i = 0; $i < $count; $i++) {
            $frame = $trace[$i];

            if (! isset($frame['file'])) {
                continue;
            }

            if (str_starts_with($frame['file'], $vendorPath)) {
                continue;
            }

            if (str_starts_with($frame['file'], $packagePath)) {
                continue;
            }

            return self::format($frame, $trace[$i + 1] ?? null);
        }

        return null;
    }

    private static function format(array $frame, ?array $context): string
    {
        $line = $frame['line'] ?? '?';

        if ($context !== null) {
            $function = $context['function'] ?? null;
            $class = $context['class'] ?? null;

            if ($function !== null && ! str_starts_with($function, '{closure')) {
                return $class
                    ? "{$class}@{$function}:{$line}"
                    : "{$function}:{$line}";
            }
        }

        return self::relativePath($frame['file']).":{$line}";
    }

    private static function relativePath(string $file): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($file, $base)
            ? substr($file, strlen($base))
            : $file;
    }
}
