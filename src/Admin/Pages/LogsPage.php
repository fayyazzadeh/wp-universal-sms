<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Admin\Pages;

final class LogsPage
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view SMS logs.', 'wp-universal-sms'));
        }

        echo '<div class="wrap wpu-sms-admin" dir="rtl">';
        echo '<div class="wpu-sms-header"><div><span class="wpu-sms-eyebrow">Logs</span><h1>SMS Activity</h1><p>Transport diagnostics will appear here without exposing credentials.</p></div></div>';
        echo '<div class="wpu-sms-panel"><table class="widefat striped"><thead><tr><th>Status</th><th>Provider</th><th>HTTP</th><th>Duration</th><th>Error</th></tr></thead><tbody>';
        echo '<tr><td colspan="5">No persisted SMS activity is available yet. The persistence layer will be added with the operational log store.</td></tr>';
        echo '</tbody></table></div></div>';
    }
}
