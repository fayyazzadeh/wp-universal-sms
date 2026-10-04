<?php

declare(strict_types=1);

namespace {
    if (!function_exists('esc_html')) { function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES); } }
    if (!function_exists('esc_attr')) { function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES); } }
    if (!function_exists('esc_textarea')) { function esc_textarea($v) { return htmlspecialchars((string) $v, ENT_QUOTES); } }
    if (!function_exists('selected')) { function selected($selected, $current, $echo = true) { $value = (string) $selected === (string) $current ? ' selected="selected"' : ''; if ($echo) echo $value; return $value; } }
    if (!function_exists('wp_nonce_field')) { function wp_nonce_field($action, $name) { echo '<input name="' . $name . '" value="nonce">'; } }
}

namespace Fayyazdeh\UniversalSms\Tests\Unit\Admin;

use Fayyazdeh\UniversalSms\Admin\AdminSettings;
use Fayyazdeh\UniversalSms\Admin\Pages\ProvidersPage;
use PHPUnit\Framework\TestCase;

final class ProvidersPageTest extends TestCase
{
    private function settings(array $stored): AdminSettings
    {
        $storage = static function (string $operation, string $key, array $value = []) use ($stored): array {
            return $operation === 'get' ? $stored : $value;
        };

        return new AdminSettings($storage);
    }

    public function test_provider_page_renders_configuration_sections_and_actions(): void
    {
        $page = new ProvidersPage($this->settings([
            'provider' => 'generic-http',
            'endpoint' => 'https://sms.example.test/send',
            'method' => 'POST',
            'sender' => '3000',
            'test_recipient' => '09120000000',
            'auth' => ['type' => 'bearer', 'value' => 'super-secret'],
            'headers' => ['Content-Type' => 'application/json'],
            'query' => ['format' => 'json'],
            'body' => ['to' => '{{mobile}}'],
            'response' => ['success_path' => 'ok', 'success_value' => true, 'message_id_path' => 'id'],
            'connection' => ['endpoint' => 'https://sms.example.test/health', 'method' => 'GET'],
            'timeout' => 30,
        ]));

        ob_start();
        $page->render();
        $html = ob_get_clean();

        self::assertStringContainsString('Basic Configuration', $html);
        self::assertStringContainsString('Authentication', $html);
        self::assertStringContainsString('Request', $html);
        self::assertStringContainsString('Mapping', $html);
        self::assertStringContainsString('Connection Test', $html);
        self::assertStringContainsString('GET', $html);
        self::assertStringContainsString('PATCH', $html);
        self::assertStringContainsString('name="wpu_sms[endpoint]"', $html);
        self::assertStringContainsString('name="wpu_sms[timeout]"', $html);
        self::assertStringContainsString('name="wpu_sms[headers][0][name]"', $html);
        self::assertStringContainsString('Save Configuration', $html);
        self::assertStringContainsString('Test Connection', $html);
        self::assertStringContainsString('Send Test SMS', $html);
        self::assertStringNotContainsString('super-secret', $html);
        self::assertStringContainsString('__WPU_SMS_SECRET_MASKED__', $html);
    }
}

}
