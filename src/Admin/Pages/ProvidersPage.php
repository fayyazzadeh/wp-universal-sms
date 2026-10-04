<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Admin\Pages;

use Fayyazdeh\UniversalSms\Admin\AdminSettings;
use Fayyazdeh\UniversalSms\Admin\ProviderDefinitions;

final class ProvidersPage
{
    public function __construct(private readonly AdminSettings $settings)
    {
    }

    public function render(): void
    {
        $definitions = ProviderDefinitions::all();
        $config = $this->settings->getRawGatewayConfig();

        echo '<div class="wrap wpu-sms-admin" dir="rtl">';
        echo '<div class="wpu-sms-header"><div><span class="wpu-sms-eyebrow">Providers</span><h1>Provider Configuration</h1><p>Choose how the plugin talks to the customer-owned SMS API.</p></div></div>';
        echo '<div class="wpu-sms-panel"><h2>Available adapters</h2>';
        foreach ($definitions as $id => $definition) {
            $active = ($config['provider'] ?? '') === $id;
            echo '<div class="wpu-sms-provider"><div><strong>' . esc_html($definition['label']) . '</strong><span>' . esc_html(strtoupper($definition['type'])) . '</span></div><div>' . ($active ? '<span class="wpu-sms-badge success">Active</span>' : '<span class="wpu-sms-badge">Available</span>') . '</div></div>';
        }
        echo '</div>';
        echo '<div class="wpu-sms-panel"><h2>Authentication modes</h2><div class="wpu-sms-chips">';
        foreach ($definitions['generic-http']['auth'] as $label) {
            echo '<span>' . esc_html($label) . '</span>';
        }
        echo '</div></div></div>';
    }
}
