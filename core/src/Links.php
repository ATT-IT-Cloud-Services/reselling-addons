<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Deep links into the Ignite customer frontend.
 *
 * The reseller is a customer of the platform, not an administrator. These links
 * open resources in the reseller's own Ignite account and are therefore only
 * shown in an admin context (WHMCS admin tab, reseller addon admin page). They
 * must never be rendered in an end-customer client area: the Ignite account
 * belongs to the reseller and would expose other customers' services.
 */
final class Links
{
    public function __construct(private readonly Config $config)
    {
    }

    public function webService(string $serviceId): ?string
    {
        return $this->join($this->config->webAppBaseUrl, '/dashboard/services/' . rawurlencode($serviceId));
    }

    public function webDashboard(): ?string
    {
        return $this->join($this->config->webAppBaseUrl, '/dashboard');
    }

    public function webBilling(): ?string
    {
        return $this->join($this->config->webAppBaseUrl, '/dashboard/billing');
    }

    public function webDomains(): ?string
    {
        return $this->join($this->config->webAppBaseUrl, '/dashboard/domains');
    }

    public function webInvoices(): ?string
    {
        return $this->join($this->config->webAppBaseUrl, '/dashboard/invoices');
    }

    public function apiDocs(): ?string
    {
        return $this->join($this->config->apiBaseUrl, '/openapi');
    }

    private function join(?string $base, string $path): ?string
    {
        if ($base === null || $base === '') {
            return null;
        }

        return rtrim($base, '/') . $path;
    }
}
