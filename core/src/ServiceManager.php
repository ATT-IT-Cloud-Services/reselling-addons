<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Service (VM / hosting / mail) lifecycle and day-2 operations.
 *
 * Read endpoints go through the raw transport because the API returns rich
 * nested payloads (capabilities, network, specs, subscription) that the typed
 * SDK models intentionally trim.
 */
final class ServiceManager
{
    /** @var array<string, string> */
    private const POWER_ACTIONS = [
        'start' => 'start',
        'stop' => 'stop',
        'restart' => 'restart',
        'reset' => 'reset',
        'suspend' => 'suspend',
        'resume' => 'resume',
    ];

    public function __construct(private readonly Api $api)
    {
    }

    /** @return array<string, mixed> */
    public function list(int $limit = 100, ?string $view = null): array
    {
        return $this->api->get('/services', array_filter([
            'limit' => $limit,
            'view' => $view,
        ], static fn ($v) => $v !== null));
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->api->get('/services/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function status(string $id, bool $live = false): array
    {
        return $this->api->get('/services/' . rawurlencode($id) . '/status', $live ? ['live' => 1] : null);
    }

    /** @return array<string, mixed> */
    public function view(string $id): array
    {
        return $this->api->get('/services/' . rawurlencode($id) . '/view');
    }

    /** @return array<string, mixed> */
    public function action(string $id, string $key, array $body = []): array
    {
        return $this->api->post('/services/' . rawurlencode($id) . '/actions/' . rawurlencode($key), $body);
    }

    /** @return array<string, mixed> */
    public function power(string $id, string $action, array $body = []): array
    {
        $key = self::POWER_ACTIONS[$action] ?? $action;

        return $this->api->post('/services/' . rawurlencode($id) . '/action', ['action' => $key] + $body);
    }

    public function suspend(string $id): array
    {
        return $this->action($id, 'suspend');
    }

    public function resume(string $id): array
    {
        return $this->action($id, 'resume');
    }

    /** @return array<string, mixed> */
    public function update(string $id, array $body): array
    {
        return $this->api->patch('/services/' . rawurlencode($id), $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $id): array
    {
        return $this->api->delete('/services/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function features(string $id): array
    {
        return $this->api->get('/services/' . rawurlencode($id) . '/features');
    }

    /** @return array<string, mixed> */
    public function settings(string $id): array
    {
        return $this->api->get('/services/' . rawurlencode($id) . '/settings');
    }

    /** @return array<string, mixed> */
    public function updateSettings(string $id, array $body): array
    {
        return $this->api->patch('/services/' . rawurlencode($id) . '/settings', $body);
    }

    /** @return array<string, mixed> */
    public function billingCycles(string $id): array
    {
        return $this->api->get('/services/' . rawurlencode($id) . '/billing-cycles');
    }

    /** @return array<string, mixed> */
    public function upgradeOptions(string $id): array
    {
        return $this->api->get('/services/' . rawurlencode($id) . '/upgrade');
    }

    /** @return array<string, mixed> */
    public function upgradePreview(string $id, array $resourceChanges): array
    {
        return $this->api->post('/services/' . rawurlencode($id) . '/upgrade-preview', ['resourceChanges' => $resourceChanges]);
    }

    /** @return array<string, mixed> */
    public function applyDowngrade(string $id, array $resourceChanges): array
    {
        return $this->api->post('/services/' . rawurlencode($id) . '/apply-downgrade', ['resourceChanges' => $resourceChanges]);
    }

    /** @return array<string, mixed> */
    public function reinstall(string $id, array $options = []): array
    {
        return $this->api->post('/services/' . rawurlencode($id) . '/reinstall', $options);
    }

    /** @return array<string, mixed> */
    public function renew(string $id, int $billingCycleDays): array
    {
        return $this->api->post('/services/' . rawurlencode($id) . '/renew', ['billingCycleDays' => $billingCycleDays]);
    }

    /** @return array<string, mixed> */
    public function console(string $id, bool $embed = false): array
    {
        $path = '/services/' . rawurlencode($id) . ($embed ? '/console/embed' : '/console');

        return $this->api->get($path);
    }

    /** @return array<string, mixed> */
    public function consoleCommand(string $id, string $command): array
    {
        return $this->api->post('/services/' . rawurlencode($id) . '/console/command', ['command' => $command]);
    }

    /** @return array<string, mixed> */
    public function sftp(string $id): array
    {
        return $this->api->get('/services/' . rawurlencode($id) . '/sftp');
    }

    /** @return array<string, mixed> */
    public function ensureSftpPassword(string $id): array
    {
        return $this->api->post('/services/' . rawurlencode($id) . '/sftp/ensure-password', []);
    }

    /** @return array<string, mixed> */
    public function rotateSftpPassword(string $id): array
    {
        return $this->api->post('/services/' . rawurlencode($id) . '/sftp/rotate-password', []);
    }

    /** @return array<string, mixed> */
    public function changePassword(string $id, string $password): array
    {
        return $this->api->patch('/services/' . rawurlencode($id) . '/settings', ['password' => $password]);
    }

    /** @return array<string, mixed> */
    public function allocations(string $id): array
    {
        return $this->api->get('/services/' . rawurlencode($id) . '/allocations');
    }

    /** @return array<string, mixed> */
    public function topIps(string $id): array
    {
        return $this->api->get('/services/' . rawurlencode($id) . '/top-ips');
    }

    /**
     * Provision a new service through the API-capable checkout.
     *
     * The checkout settles payment server-side (balance, BusinessFund or
     * invoice) and provisions only once payment is settled. API keys may only
     * use non-interactive methods and must send an `Idempotency-Key`, so a retry
     * returns the stored result instead of ordering twice.
     *
     * @param array{
     *   productId: string,
     *   providerId: string,
     *   billingCycleDays: int,
     *   paymentMethod?: string,
     *   idempotencyKey?: string,
     *   couponCode?: string,
     *   billingAddressId?: string,
     *   locationId?: string,
     *   templateId?: string,
     *   customization?: array<string, mixed>
     * } $options
     * @return array{subscriptionId: ?string, serviceId: ?string, invoiceId: ?string, status: ?string, raw: array<string, mixed>}
     */
    public function create(array $options): array
    {
        $paymentMethod = (string) ($options['paymentMethod'] ?? 'guthaben');
        $idempotencyKey = trim((string) ($options['idempotencyKey'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = 'ignite-reseller-' . bin2hex(random_bytes(12));
        }

        $item = [
            'productId' => (string) $options['productId'],
            'providerId' => (string) $options['providerId'],
            'quantity' => 1,
            'billingCycle' => (int) $options['billingCycleDays'],
            'itemType' => 'new',
        ];
        if (!empty($options['locationId'])) {
            $item['locationId'] = (string) $options['locationId'];
        }
        if (!empty($options['templateId'])) {
            $item['templateId'] = (string) $options['templateId'];
        }
        if (is_array($options['customization'] ?? null)) {
            $item['customization'] = $options['customization'];
        }

        $body = [
            'items' => [$item],
            'paymentMethod' => $paymentMethod,
        ];
        if (!empty($options['couponCode'])) {
            $body['couponCode'] = (string) $options['couponCode'];
        }
        if (!empty($options['billingAddressId'])) {
            $body['billingAddressId'] = (string) $options['billingAddressId'];
        }

        // Machine checkout: API keys are limited to non-interactive payment
        // methods and every request needs an Idempotency-Key so retries cannot
        // order or charge twice.
        $raw = $this->api->post('/checkout', $body, ['Idempotency-Key' => $idempotencyKey]);

        $subscriptionIds = is_array($raw['subscriptionIds'] ?? null) ? $raw['subscriptionIds'] : [];
        $subscriptionId = isset($subscriptionIds[0]) ? (string) $subscriptionIds[0] : null;

        return [
            'subscriptionId' => $subscriptionId,
            'serviceId' => $this->resolveServiceIdForSubscription($subscriptionId),
            'invoiceId' => isset($raw['invoiceId']) ? (string) $raw['invoiceId'] : null,
            'status' => null,
            'raw' => $raw,
        ];
    }

    /**
     * The create response omits the service id, so resolve it from the
     * subscription list (the user-subscription response exposes service.id).
     */
    public function resolveServiceIdForSubscription(?string $subscriptionId): ?string
    {
        if ($subscriptionId === null) {
            return null;
        }

        // Checkout provisions synchronously, but allow a short settle window.
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $list = $this->api->get('/user/subscriptions');
            foreach ($list['subscriptions'] ?? [] as $subscription) {
                if (!is_array($subscription) || (string) ($subscription['id'] ?? '') !== $subscriptionId) {
                    continue;
                }
                $service = $subscription['service'] ?? null;
                if (is_array($service) && !empty($service['id'])) {
                    return (string) $service['id'];
                }
            }

            usleep(1_000_000);
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function capabilities(string $id): array
    {
        $detail = $this->get($id);

        return is_array($detail['capabilities'] ?? null) ? $detail['capabilities'] : [];
    }
}
