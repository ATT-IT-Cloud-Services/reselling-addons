<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Facade over the whole reselling core. One instance per request/config.
 */
final class Reseller
{
    public readonly Api $api;
    public readonly Catalog $catalog;
    public readonly Links $links;
    public readonly ServiceManager $services;
    public readonly SubscriptionManager $subscriptions;
    public readonly DomainManager $domains;
    public readonly ReverseDnsManager $reverseDns;
    public readonly SshKeyManager $sshKeys;
    public readonly BillingManager $billing;
    public readonly MailManager $mail;
    public readonly PleskManager $plesk;
    public readonly BackupManager $backups;

    public function __construct(public readonly Config $config)
    {
        $this->api = new Api($config);
        $this->catalog = new Catalog($this->api, $config, $this->api->cache());
        $this->links = new Links($config);
        $this->services = new ServiceManager($this->api);
        $this->subscriptions = new SubscriptionManager($this->api);
        $this->domains = new DomainManager($this->api);
        $this->reverseDns = new ReverseDnsManager($this->api);
        $this->sshKeys = new SshKeyManager($this->api);
        $this->billing = new BillingManager($this->api);
        $this->mail = new MailManager($this->api);
        $this->plesk = new PleskManager($this->api);
        $this->backups = new BackupManager($this->api);
    }

    /** @return array{ok: bool, message: string} */
    public function testConnection(): array
    {
        try {
            $this->api->get('/ping');

            return ['ok' => true, 'message' => 'Connection established.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => ErrorTranslator::message($e, $this->config->locale)];
        }
    }
}
