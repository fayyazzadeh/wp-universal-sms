<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Admin;

final class ProviderDefinitions
{
    public static function all(): array
    {
        return [
            'generic-http' => [
                'label' => 'Generic HTTP / REST API',
                'type' => 'http',
                'methods' => ['GET', 'POST', 'PUT', 'PATCH'],
                'auth' => [
                    'none' => 'None',
                    'header' => 'Custom Header',
                    'api_key' => 'API Key',
                    'query' => 'Query Parameter',
                    'bearer' => 'Bearer Token',
                    'basic' => 'Basic Authentication',
                ],
            ],
        ];
    }
}
