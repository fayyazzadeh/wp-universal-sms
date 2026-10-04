<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Providers;

final class RequestTemplate
{
    public static function interpolate(mixed $value, array $variables): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = self::interpolate($item, $variables);
            }
            return $result;
        }

        if (!is_string($value)) {
            return $value;
        }

        return preg_replace_callback(
            '/{{\\s*([a-zA-Z0-9_]+)\\s*}}/',
            static fn (array $match): string => (string) ($variables[$match[1]] ?? ''),
            $value
        );
    }
}
