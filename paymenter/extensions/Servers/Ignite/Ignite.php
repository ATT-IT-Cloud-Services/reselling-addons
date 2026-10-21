<?php

declare(strict_types=1);

namespace Paymenter\Extensions\Servers\Ignite;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Server;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use IgniteReseller\Config;
use IgniteReseller\ErrorTranslator;
use IgniteReseller\Reseller;
use Paymenter\Extensions\Servers\Ignite\Lib\ServiceActions;

/**
 * Paymenter server extension for Ignite.
 *
 * Plain reselling: Proxmox, Plesk, Pterodactyl, Mailcow and domains.
 *
 * The reseller is an Ignite customer, not a platform administrator. The client
 * area therefore only ever exposes data of the one mapped service; account-wide
 * resources (all services, domains, SSH keys, subscriptions, invoices) stay out
 * of the client area and are handled by the reseller's own admin interface.
 */
#[ExtensionMeta(
    name: 'Ignite',
    description: 'Reseller integration for the Ignite hosting platform (Proxmox, Plesk, Pterodactyl, Mailcow, domains).',
    version: '1.1.0',
    author: 'Ignite',
    url: 'https://www.<platform-domain>',
    extensionId: 'ignite-server',
)]
class Ignite extends Server
{
    public function boot(): void
    {
        View::addNamespace('ignite', __DIR__ . '/resources/views');
        Lang::addNamespace('ignite', __DIR__ . '/resources/lang');

        $routes = __DIR__ . '/routes/web.php';
        if (is_file($routes)) {
            require $routes;
        }
    }

    // --- Configuration -----------------------------------------------------

    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'api_base_url',
                'label' => 'API Base URL',
                'type' => 'text',
                'required' => true,
                'description' => 'Host-root API base URL, e.g. https://api.<platform-domain>',
            ],
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'encrypted' => true,
                'description' => 'Ignite API key (ek_…), scopes read/write + services.read/services.write.',
            ],
            [
                'name' => 'web_app_base_url',
                'label' => 'Web App Base URL',
                'type' => 'text',
                'description' => 'Ignite customer frontend, e.g. https://www.<platform-domain>. Admin links only.',
            ],
            [
                'name' => 'locale',
                'label' => 'Language',
                'type' => 'select',
                'options' => ['de' => 'Deutsch', 'en' => 'English'],
                'default' => 'de',
            ],
            [
                'name' => 'sandbox',
                'label' => 'Sandbox mode',
                'type' => 'checkbox',
                'default' => false,
            ],
            [
                'name' => 'catalog_ttl_seconds',
                'label' => 'Catalog cache TTL (seconds)',
                'type' => 'number',
                'default' => 900,
            ],
        ];
    }

    public function getProductConfig($values = []): array
    {
        $options = [];
        try {
            $catalog = $this->reseller()->catalog;
            foreach ($catalog->flatten() as $entry) {
                $type = $catalog->providerType($entry);
                if ($type !== null && in_array($type, \IgniteReseller\Catalog::supportedProviderTypes(), true)) {
                    $options[$entry['key']] = $entry['categoryPath'] . ' / ' . $entry['name'];
                }
            }
        } catch (\Throwable) {
            $options = [];
        }

        return [
            [
                'name' => 'api_product',
                'label' => 'API product',
                'type' => 'select',
                'options' => $options,
                'required' => true,
                'description' => 'Category/product from the live Ignite catalog.',
            ],
            [
                'name' => 'billing_cycle_days',
                'label' => 'Billing cycle (days)',
                'type' => 'select',
                'options' => [
                    7 => '7', 14 => '14', 30 => '30', 60 => '60',
                    90 => '90', 120 => '120', 365 => '365',
                ],
                'default' => 30,
                'required' => true,
            ],
            [
                'name' => 'coupon_code',
                'label' => 'Coupon code (price reduction)',
                'type' => 'text',
                'description' => 'Optional, applied at order time.',
            ],
            [
                'name' => 'payment_method',
                'label' => 'Payment method',
                'type' => 'select',
                'options' => ['guthaben' => 'Balance', 'businessfund' => 'BusinessFund', 'invoice' => 'Invoice'],
                'default' => 'guthaben',
                'required' => true,
                'description' => 'Non-interactive payment method used by the automatic checkout.',
            ],
            [
                'name' => 'template',
                'label' => 'OS/Template',
                'type' => 'text',
            ],
            [
                'name' => 'location',
                'label' => 'Location id',
                'type' => 'text',
            ],
            [
                'name' => 'ssh_key_ids',
                'label' => 'SSH key ids (comma separated)',
                'type' => 'text',
            ],

        ];
    }

    public function getCheckoutConfig(Product $product, $values = [], $settings = []): array
    {
        $sshKeys = [];
        try {
            foreach ($this->reseller()->sshKeys->keys() as $key) {
                $sshKeys[(string) $key['id']] = (string) ($key['name'] ?? $key['id']);
            }
        } catch (\Throwable) {
            $sshKeys = [];
        }

        return [
            [
                'name' => 'hostname',
                'label' => 'Hostname',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'ssh_key_id',
                'label' => 'SSH key',
                'type' => 'select',
                'options' => ['' => '– none –'] + $sshKeys,
                'required' => false,
            ],
        ];
    }

    public function testConfig(): bool|string
    {
        try {
            $result = $this->reseller()->testConnection();

            return $result['ok'] ? true : $result['message'];
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    // --- Lifecycle ---------------------------------------------------------

    public function createServer(Service $service, $settings, $properties)
    {
        $reseller = $this->reseller();
        $settings = $this->merge($settings, $properties);

        $entry = $reseller->catalog->find((string) ($settings['api_product'] ?? ''));
        if ($entry === null) {
            throw new \RuntimeException('No API product mapped for this product.');
        }

        $providerId = $reseller->catalog->providerId($entry);
        if ($providerId === null) {
            throw new \RuntimeException('Could not resolve the provider for the selected product.');
        }

        $paymentMethod = (string) ($settings['payment_method'] ?? 'guthaben');
        if (!in_array($paymentMethod, ['guthaben', 'businessfund', 'invoice'], true)) {
            $paymentMethod = 'guthaben';
        }

        $result = $reseller->services->create([
            'productId' => (string) $entry['id'],
            'providerId' => $providerId,
            'billingCycleDays' => (int) ($settings['billing_cycle_days'] ?? 30),
            'couponCode' => $settings['coupon_code'] ?? null,
            'paymentMethod' => $paymentMethod,
            'idempotencyKey' => 'paymenter-' . $service->id,
        ]);

        if ($result['serviceId'] === null) {
            throw new \RuntimeException('The API did not return a service id.');
        }

        $this->storeRemoteServiceId($service, $result['serviceId']);

        $hostname = $settings['hostname'] ?? null;
        if (is_string($hostname) && $hostname !== '') {
            try {
                $reseller->services->update($result['serviceId'], ['hostname' => $hostname]);
            } catch (\Throwable) {
                // Non-fatal.
            }
        }

        $sshId = $settings['ssh_key_id'] ?? null;
        if (is_string($sshId) && $sshId !== '') {
            try {
                $reseller->sshKeys->assignToService($result['serviceId'], [$sshId]);
            } catch (\Throwable) {
                // Non-fatal: the service is provisioned, the key can be attached later.
            }
        }

        return true;
    }

    public function suspendServer(Service $service, $settings, $properties)
    {
        $this->reseller()->services->suspend($this->requireRemoteId($service));

        return true;
    }

    public function unsuspendServer(Service $service, $settings, $properties)
    {
        $this->reseller()->services->resume($this->requireRemoteId($service));

        return true;
    }

    public function terminateServer(Service $service, $settings, $properties)
    {
        $id = $this->remoteServiceId($service);
        if ($id === null) {
            return true;
        }

        $reseller = $this->reseller();
        try {
            $reseller->subscriptions->cancel($id, ['immediate' => true]);
        } catch (\Throwable) {
            // Already cancelled.
        }
        $reseller->services->delete($id);

        return true;
    }

    public function upgradeServer(Service $service, $settings, $properties)
    {
        // Product/resource upgrades must go through the subscription interface;
        // the API key cannot use checkout. Preview is validated here.
        $this->reseller()->services->upgradeOptions($this->requireRemoteId($service));

        return true;
    }

    // --- Client surface (per service only) --------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function getActions(Service $service, $settings = null, $properties = null): array
    {
        $id = $this->remoteServiceId($service);
        if ($id === null) {
            return [];
        }

        try {
            $reseller = $this->reseller();
            $detail = $reseller->services->get($id);
            $server = is_array($detail['server'] ?? null) ? $detail['server'] : [];
        } catch (\Throwable $e) {
            return [[
                'type' => 'text',
                'label' => 'Error',
                'value' => ErrorTranslator::message($e, (string) $this->config('locale') ?: 'de'),
            ]];
        }

        $providerType = is_string($server['provider']['type'] ?? null) ? $server['provider']['type'] : null;

        $actions = [
            ['type' => 'button', 'label' => 'Start', 'function' => 'powerStart'],
            ['type' => 'button', 'label' => 'Restart', 'function' => 'powerRestart'],
            ['type' => 'button', 'label' => 'Stop', 'function' => 'powerStop'],
            ['type' => 'view', 'label' => 'Overview', 'name' => 'overview'],
            ['type' => 'view', 'label' => 'Reverse DNS', 'name' => 'rdns'],
            ['type' => 'view', 'label' => 'Backups', 'name' => 'backups'],
        ];

        // DNS is only offered for a service that owns a managed domain zone.
        $ownDomain = $server['domainName'] ?? null;
        if (is_string($ownDomain) && $ownDomain !== '') {
            $actions[] = ['type' => 'view', 'label' => 'DNS', 'name' => 'dns'];
        }

        if ($providerType === 'mailcow') {
            $actions[] = ['type' => 'view', 'label' => 'E-Mail', 'name' => 'mail'];
        }
        if ($providerType === 'plesk') {
            $actions[] = ['type' => 'view', 'label' => 'Plesk', 'name' => 'plesk'];
        }

        $actions[] = ['type' => 'button', 'label' => 'Create backup', 'function' => 'backupCreate'];

        $actions[] = ['type' => 'text', 'label' => 'Status', 'value' => (string) ($server['status'] ?? '')];
        $actions[] = ['type' => 'text', 'label' => 'IPv4', 'value' => (string) ($server['network']['primaryIpv4'] ?? '')];
        $actions[] = ['type' => 'text', 'label' => 'IPv6', 'value' => (string) ($server['network']['primaryIpv6'] ?? '')];

        return $actions;
    }

    public function getView(Service $service, $settings = null, $properties = null, $view = null)
    {
        $id = $this->requireRemoteId($service);
        $reseller = $this->reseller();

        if (is_array($view)) {
            $name = (string) ($view['name'] ?? 'overview');
        } elseif (is_object($view)) {
            $name = (string) ($view->name ?? 'overview');
        } else {
            $name = (string) ($view ?? 'overview');
        }
        if ($name === '') {
            $name = 'overview';
        }

        $data = [
            'serviceId' => $id,
            'detail' => [],
            'rdns' => [],
            'dns' => [],
            'dnsDomain' => null,
            'dnsDomainId' => null,
            'mail' => [],
            'backups' => [],
            'plesk' => [],
            'error' => null,
        ];

        try {
            $detail = $reseller->services->get($id);
            $data['detail'] = is_array($detail['server'] ?? null) ? $detail['server'] : [];

            if ($name === 'rdns') {
                $data['rdns'] = $reseller->reverseDns->forService($id);
            } elseif ($name === 'dns') {
                $dns = ServiceActions::dnsOverview($id);
                $data['dns'] = $dns['records'];
                $data['dnsDomain'] = $dns['domainName'];
                $data['dnsDomainId'] = $dns['domainId'];
            } elseif ($name === 'backups') {
                $backups = $reseller->backups->list($id);
                $data['backups'] = $backups['backups'] ?? $backups['data'] ?? [];
            } elseif ($name === 'mail') {
                $data['mail'] = [
                    'domains' => $reseller->mail->domains($id),
                    'mailboxes' => $reseller->mail->mailboxes($id),
                    'aliases' => $reseller->mail->aliases($id),
                ];
            } elseif ($name === 'plesk') {
                $data['plesk'] = [
                    'setup' => $reseller->plesk->setup($id),
                    'domains' => $reseller->plesk->domains($id),
                ];
            }
        } catch (\Throwable $e) {
            $data['error'] = ErrorTranslator::message($e, (string) ($this->config('locale') ?: 'de'));
        }

        return view('ignite::' . $name, $data);
    }

    // --- Custom actions (per service only) ---------------------------------

    public function powerStart(Service $service, $settings = null, $properties = null)
    {
        return $this->power($service, 'start');
    }

    public function powerStop(Service $service, $settings = null, $properties = null)
    {
        return $this->power($service, 'stop');
    }

    public function powerRestart(Service $service, $settings = null, $properties = null)
    {
        return $this->power($service, 'restart');
    }

    private function power(Service $service, string $action): bool
    {
        $this->reseller()->services->power($this->requireRemoteId($service), $action);

        return true;
    }

    public function rdnsSet(Service $service, $settings = null, $properties = null)
    {
        $id = $this->requireRemoteId($service);
        $entryId = (string) request()->input('entry_id');
        $reseller = $this->reseller();

        foreach ($reseller->reverseDns->forService($id) as $entry) {
            if ((string) ($entry['id'] ?? '') === $entryId) {
                $reseller->reverseDns->setReverse($entryId, (string) request()->input('reverse_content'));

                return true;
            }
        }

        throw new \RuntimeException('This IP entry does not belong to the service.');
    }

    public function mailApply(Service $service, $settings = null, $properties = null)
    {
        $this->reseller()->mail->applyDns($this->requireRemoteId($service), []);

        return true;
    }

    public function mailVerify(Service $service, $settings = null, $properties = null)
    {
        $this->reseller()->mail->verifyDns($this->requireRemoteId($service), []);

        return true;
    }

    public function pleskRotate(Service $service, $settings = null, $properties = null)
    {
        $this->reseller()->plesk->rotateCredentials($this->requireRemoteId($service));

        return true;
    }

    public function backupCreate(Service $service, $settings = null, $properties = null)
    {
        $this->reseller()->backups->create($this->requireRemoteId($service), []);

        return true;
    }

    public function backupRestore(Service $service, $settings = null, $properties = null)
    {
        $id = $this->requireRemoteId($service);
        $backupId = (string) request()->input('backup_id');
        $reseller = $this->reseller();

        if (!$this->backupBelongsToService($reseller, $id, $backupId)) {
            throw new \RuntimeException('This backup does not belong to the service.');
        }

        $reseller->backups->restore($id, $backupId, []);

        return true;
    }

    public function backupDelete(Service $service, $settings = null, $properties = null)
    {
        $id = $this->requireRemoteId($service);
        $backupId = (string) request()->input('backup_id');
        $reseller = $this->reseller();

        if (!$this->backupBelongsToService($reseller, $id, $backupId)) {
            throw new \RuntimeException('This backup does not belong to the service.');
        }

        $reseller->backups->delete($id, $backupId);

        return true;
    }

    private function backupBelongsToService(Reseller $reseller, string $serviceId, string $backupId): bool
    {
        if ($backupId === '') {
            return false;
        }

        $list = $reseller->backups->list($serviceId);
        foreach ($list['backups'] ?? $list['data'] ?? [] as $backup) {
            if (is_array($backup) && (string) ($backup['id'] ?? '') === $backupId) {
                return true;
            }
        }

        return false;
    }

    // --- Helpers -----------------------------------------------------------

    public function reseller(): Reseller
    {
        return new Reseller(Config::fromArray([
            'apiBaseUrl' => (string) ($this->config('api_base_url') ?? ''),
            'apiKey' => (string) ($this->config('api_key') ?? ''),
            'webAppBaseUrl' => $this->config('web_app_base_url'),
            'locale' => (string) ($this->config('locale') ?? 'de'),
            'sandbox' => (bool) $this->config('sandbox'),
            'catalogTtlSeconds' => (int) ($this->config('catalog_ttl_seconds') ?? 900),
        ]));
    }

    private function requireRemoteId(Service $service): string
    {
        $id = $this->remoteServiceId($service);
        if ($id === null) {
            throw new \RuntimeException('The Ignite service is not provisioned yet.');
        }

        return $id;
    }

    public function remoteServiceId(Service $service): ?string
    {
        try {
            $value = $service->properties()->where('key', 'ignite_service_id')->value('value');
        } catch (\Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function storeRemoteServiceId(Service $service, string $serviceId): void
    {
        $service->properties()->where('key', 'ignite_service_id')->delete();
        $service->properties()->create('key', 'ignite_service_id')->delete();
        $service->properties()->create([
            'key' => 'ignite_service_id',
            'value' => $serviceId,
        ]);
    }

    /** @param mixed $settings @param mixed $properties @return array<string, mixed> */
    private function merge($settings, $properties): array
    {
        $settings = is_array($settings) ? $settings : [];
        $properties = is_array($properties) ? $properties : [];

        return array_merge($settings, $properties);
    }
}
