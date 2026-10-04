<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Providers;

final class ResponseMapper
{
    public static function get(mixed $data, ?string $path): mixed
    {
        if ($path === null || $path === '') {
            return null;
        }

        foreach (explode('.', $path) as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } else {
                return null;
            }
        }

        return $data;
    }
}
