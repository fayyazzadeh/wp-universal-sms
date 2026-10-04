<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms;

use Fayyazdeh\UniversalSms\Admin\AdminActions;
use Fayyazdeh\UniversalSms\Admin\AdminMenu;
use Fayyazdeh\UniversalSms\Admin\AdminSettings;
use Fayyazdeh\UniversalSms\Core\ProviderRegistry;
use Fayyazdeh\UniversalSms\Core\SMS;

final class Plugin
{
    private static ?self $instance = null;

    private function __construct(
        private readonly SMS $sms,
        private readonly ProviderRegistry $registry
    ) {
    }

    public static function boot(): self
    {
        if (self::$instance === null) {
            $registry = new ProviderRegistry();
            self::$instance = new self(new SMS($registry), $registry);

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
        (new AdminActions(
            new AdminSettings(),
            $this->registry,
            $this->sms
        ))->handleRequest();
    }
}
