<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms;

use Fayyazdeh\UniversalSms\Admin\AdminMenu;
use Fayyazdeh\UniversalSms\Admin\AdminSettings;
use Fayyazdeh\UniversalSms\Core\ProviderRegistry;
use Fayyazdeh\UniversalSms\Core\SMS;

final class Plugin
{
    private static ?self $instance = null;

    private function __construct(private readonly SMS $sms)
    {
    }

    public static function boot(): self
    {
        if (self::$instance === null) {
            $registry = new ProviderRegistry();
            self::$instance = new self(new SMS($registry));

            if (function_exists('add_action')) {
                add_action('admin_menu', [self::$instance, 'registerAdmin']);
                add_action('admin_enqueue_scripts', [self::$instance, 'enqueueAdminAssets']);
                add_action('admin_init', [self::$instance, 'handleAdminActions']);
            }
        }

        return self::$instance;
    }

    public function sms(): SMS
    {
        return $this->sms;
    }

    public function registerAdmin(): void
    {
        (new AdminMenu(new AdminSettings()))->register();
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if (strpos($hook, 'wp-universal-sms') === false) {
            return;
        }

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'wpu-sms-admin',
                plugins_url('../assets/admin.css', __FILE__),
                [],
                '0.2.0-beta'
            );
        }

        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script(
                'wpu-sms-admin',
                plugins_url('../assets/admin.js', __FILE__),
                [],
                '0.2.0-beta',
                true
            );
        }
    }

    public function handleAdminActions(): void
    {
        if (!isset($_POST['wpu_sms_action']) || $_POST['wpu_sms_action'] !== 'save_gateway') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('wpu_sms_save_gateway', 'wpu_sms_nonce');

        $input = isset($_POST['wpu_sms']) && is_array($_POST['wpu_sms'])
            ? $_POST['wpu_sms']
            : [];

        $settings = new AdminSettings();
        $current = $settings->getRawGatewayConfig();
        $settings->saveGatewayConfig(array_merge($current, [
            'provider' => sanitize_text_field($input['provider'] ?? ''),
            'sender' => sanitize_text_field($input['sender'] ?? ''),
            'test_recipient' => sanitize_text_field($input['test_recipient'] ?? ''),
        ]));

        if (function_exists('add_settings_error')) {
            add_settings_error(
                'wpu_sms',
                'gateway_saved',
                'Gateway settings saved.',
                'updated'
            );
        }
    }
}
