<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Recurring billing: the subscriptions attached to the services ordered on the
 * reseller account, plus the lifecycle actions the customer area exposes.
 * Financial actions (cancel, extend, interval, autopay, accelerate, withdraw)
 * require the desktop client header, which the reseller integration sends.
 */
final class SubscriptionManager
{
    public function __construct(private readonly Api $api)
    {
    }

    /** @return array<string, mixed> */
    public function listMine(): array
    {
        return $this->api->get('/user/subscriptions');
    }

    /** @return array<string, mixed> */
    public function create(array $options): array
    {
        return $this->api->post('/user/subscriptions', $options);
    }

    /** @return array<string, mixed> */
    public function get(string $serviceId): array
    {
        return $this->api->get('/services/' . rawurlencode($serviceId) . '/subscription');
    }

    public function cancel(string $serviceId, array $body = []): array
    {
        return $this->api->post('/services/' . rawurlencode($serviceId) . '/subscription/cancel', $body);
    }

    public function withdraw(string $serviceId, array $body = []): array
    {
        return $this->api->post('/services/' . rawurlencode($serviceId) . '/subscription/withdraw', $body);
    }

    public function reactivate(string $serviceId): array
    {
        return $this->api->post('/services/' . rawurlencode($serviceId) . '/subscription/reactivate', []);
    }

    public function accelerate(string $serviceId): array
    {
        return $this->api->post('/services/' . rawurlencode($serviceId) . '/subscription/accelerate', []);
    }

    public function extend(string $serviceId, array $body = []): array
    {
        return $this->api->post('/services/' . rawurlencode($serviceId) . '/subscription/extend', $body);
    }

    public function setInterval(string $serviceId, int $billingCycleDays): array
    {
        return $this->api->patch('/services/' . rawurlencode($serviceId) . '/subscription/interval', ['billingCycleDays' => $billingCycleDays]);
    }

    public function setAutopay(string $serviceId, array $options): array
    {
        return $this->api->patch('/services/' . rawurlencode($serviceId) . '/subscription/autopay', $options);
    }

    public function setBillingAddress(string $serviceId, ?string $billingAddressId): array
    {
        return $this->api->patch('/services/' . rawurlencode($serviceId) . '/subscription/billing-address', ['billingAddressId' => $billingAddressId]);
    }

    /**
     * Best-effort lookup of the subscription that belongs to a service.
     *
     * @return array<string, mixed>|null
     */
    public function forService(string $serviceId): ?array
    {
        $list = $this->listMine();
        foreach ($list['subscriptions'] ?? [] as $subscription) {
            if (!is_array($subscription)) {
                continue;
            }
            $service = $subscription['service'] ?? null;
            if (is_array($service) && (string) ($service['id'] ?? '') === $serviceId) {
                return $subscription;
            }
        }

        return null;
    }
}
