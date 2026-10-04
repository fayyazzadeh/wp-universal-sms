<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Admin\Pages;

use Fayyazdeh\UniversalSms\Admin\AdminSettings;

final class DashboardPage
{
    public function __construct(private readonly AdminSettings $settings)
    {
    }

    public function render(): void
    {
        $config = $this->settings->getGatewayConfig();
        $provider = $config['provider'] !== '' ? $config['provider'] : 'Not configured';

        echo '<div class="wrap wpu-sms-admin" dir="rtl">';
        echo '<div class="wpu-sms-header"><div><span class="wpu-sms-eyebrow">WP Universal SMS</span><h1>SMS Gateway Dashboard</h1><p>Manage your customer-owned SMS connection from one place.</p></div></div>';
        echo '<div class="wpu-sms-grid">';
        $this->card('Gateway', $provider, $config['sender'] !== '' ? 'Sender: ' . $config['sender'] : 'Sender not configured');
        $this->card('Status', $provider === 'Not configured' ? 'Needs setup' : 'Configured', 'Connection health is shown after testing.');
        $this->card('Security', 'Protected', 'Secrets are masked in the admin read model and logs.');
        echo '</div>';
        echo '<div class="wpu-sms-panel"><h2>Quick actions</h2><div class="wpu-sms-actions"><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=wp-universal-sms-gateway')) . '">Configure Gateway</a><a class="button" href="' . esc_url(admin_url('admin.php?page=wp-universal-sms-providers')) . '">Manage Providers</a><a class="button" href="' . esc_url(admin_url('admin.php?page=wp-universal-sms-logs')) . '">View Logs</a></div></div>';
        echo '</div>';
    }

    private function card(string $title, string $value, string $meta): void
    {
        echo '<section class="wpu-sms-card"><span>' . esc_html($title) . '</span><strong>' . esc_html($value) . '</strong><small>' . esc_html($meta) . '</small></section>';
    }
}
