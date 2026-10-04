<?php

declare(strict_types=1);

namespace Fayyazdeh\UniversalSms\Admin;

use Closure;
use Fayyazdeh\UniversalSms\Contracts\SMSProviderInterface;
use Fayyazdeh\UniversalSms\Core\ProviderRegistry;
use Fayyazdeh\UniversalSms\Core\SMS;
use Fayyazdeh\UniversalSms\Providers\GenericHttpProvider;
use InvalidArgumentException;

final class AdminActions
{
    private const TEST_MESSAGE = 'WP Universal SMS connection test.';

    public function __construct(
        private readonly AdminSettings $settings,
        private readonly ProviderRegistry $registry,
        private readonly SMS $sms,
        private readonly ?Closure $providerFactory = null,
        private readonly ?Closure $capabilityCheck = null,
        private readonly ?Closure $nonceCheck = null,
    ) {
    }

    public function handleRequest(): void
    {
        $action = isset($_POST['wpu_sms_action']) ? (string) $_POST['wpu_sms_action'] : '';
        if (!in_array($action, ['save_gateway', 'test_connection', 'send_test_sms'], true)) {
            return;
        }

        if (!$this->canManage()) {
            return;
        }

        $this->verifyNonce();

        $input = isset($_POST['wpu_sms']) && is_array($_POST['wpu_sms']) ? $this->sanitize($_POST['wpu_sms']) : [];

        try {
            $result = $this->dispatch($action, $input);
            if (function_exists('add_settings_error')) {
                add_settings_error('wpu_sms', 'gateway_action', $result['message'], $result['success'] ? 'updated' : 'error');
            }
        } catch (InvalidArgumentException $exception) {
            if (function_exists('add_settings_error')) {
                add_settings_error('wpu_sms', 'gateway_validation', $exception->getMessage(), 'error');
            }
        }
    }

    /** @param array<string,mixed> $input @return array{success:bool,message:string} */
    public function dispatch(string $action, array $input): array
    {
        if (!in_array($action, ['save_gateway', 'test_connection', 'send_test_sms'], true)) {
            return ['success' => false, 'message' => 'Unknown gateway action.'];
        }

        $providerId = (string) ($input['provider'] ?? '');
        $definitions = ProviderDefinitions::all();
        $definition = $definitions[$providerId] ?? null;
        if (!is_array($definition)) {
            throw new InvalidArgumentException('The selected SMS provider is not registered.');
        }

        $normalized = GatewayConfig::validate($input, $definition, $this->settings->getRawGatewayConfig());
        $this->settings->saveGatewayConfig($normalized);

        if ($action === 'save_gateway') {
            return ['success' => true, 'message' => 'Gateway configuration saved.'];
        }

        $provider = $this->provider($providerId, $normalized);
        $this->registry->register($provider);
        $this->registry->setDefault($providerId);

        if ($action === 'test_connection') {
            return $provider->testConnection()
                ? ['success' => true, 'message' => 'Connection test succeeded. No SMS was sent.']
                : ['success' => false, 'message' => 'Connection test failed. No SMS was sent.'];
        }

        $recipient = trim((string) ($normalized['test_recipient'] ?? ''));
        if ($recipient === '') {
            throw new InvalidArgumentException('A test recipient is required before sending a test SMS.');
        }

        $response = $this->sms->send($recipient, self::TEST_MESSAGE, $providerId);

        return $response->success
            ? ['success' => true, 'message' => 'Test SMS sent successfully.']
            : ['success' => false, 'message' => 'Test SMS failed (' . ($response->errorCode ?? 'provider_error') . ').'];
    }

    private function provider(string $providerId, array $config): SMSProviderInterface
    {
        if ($this->providerFactory !== null) {
            $provider = ($this->providerFactory)($providerId, $config);
            if ($provider instanceof SMSProviderInterface) {
                return $provider;
            }
        }

        if ($providerId === 'generic-http') {
            return new GenericHttpProvider($providerId, $config);
        }

        throw new InvalidArgumentException('The selected SMS provider is not supported by the admin gateway.');
    }

    private function canManage(): bool
    {
        if ($this->capabilityCheck !== null) {
            return (bool) ($this->capabilityCheck)();
        }

        return function_exists('current_user_can') && current_user_can('manage_options');
    }

    private function verifyNonce(): void
    {
        if ($this->nonceCheck !== null) {
            ($this->nonceCheck)();
            return;
        }

        if (function_exists('check_admin_referer')) {
            check_admin_referer('wpu_sms_save_gateway', 'wpu_sms_nonce');
        }
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[(string) $key] = $this->sanitize($item);
            }
            return $result;
        }

        if (is_scalar($value)) {
            return function_exists('sanitize_text_field') ? sanitize_text_field((string) $value) : strip_tags((string) $value);
        }

        return '';
    }
}
