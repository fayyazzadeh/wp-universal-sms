<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Admin;

final class AdminMenu
{
    public function __construct(
        private readonly AdminSettings $settings,
        private readonly string $capability = 'manage_options'
    ) {
    }

    public function register(): void
    {
        if (!function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            'WP Universal SMS',
            'Universal SMS',
            $this->capability,
            'wp-universal-sms',
            [$this, 'renderDashboard'],
            'dashicons-email-alt',
            56
        );

        add_submenu_page(
            'wp-universal-sms',
            'Dashboard',
            'Dashboard',
            $this->capability,
            'wp-universal-sms',
            [$this, 'renderDashboard']
        );

        add_submenu_page(
            'wp-universal-sms',
            'Gateway',
            'Gateway',
            $this->capability,
            'wp-universal-sms-gateway',
            [$this, 'renderGateway']
        );

        add_submenu_page(
            'wp-universal-sms',
            'Providers',
            'Providers',
            $this->capability,
            'wp-universal-sms-providers',
            [$this, 'renderProviders']
        );

        add_submenu_page(
            'wp-universal-sms',
            'Logs',
            'Logs',
            $this->capability,
            'wp-universal-sms-logs',
            [$this, 'renderLogs']
        );
    }

    public function renderDashboard(): void
    {
        (new Pages\DashboardPage($this->settings))->render();
    }

    public function renderGateway(): void
    {
        (new Pages\GatewayPage($this->settings))->render();
    }

    public function renderProviders(): void
    {
        (new Pages\ProvidersPage($this->settings))->render();
    }

    public function renderLogs(): void
    {
        (new Pages\LogsPage())->render();
    }

    public function capability(): string
    {
        return $this->capability;
    }
}
