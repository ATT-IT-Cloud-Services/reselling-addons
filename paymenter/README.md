# Paymenter reselling extension for Ignite

A Paymenter 1.x server extension for plain Ignite reselling: **Proxmox, Plesk,
Pterodactyl, Mailcow and domains**. It provisions services through the machine
subscription endpoint and exposes per-service management in the client area.

## Audience and scope (important)

The reseller is an **Ignite customer, not a platform administrator**. Their
admin interface is Paymenter itself. Therefore:

- The client area only exposes data of the **one mapped service**: overview,
  power, DNS records for the service's own domain, reverse DNS for the service
  IPs, mail, Plesk, backups. No account-wide lists (no all-services,
  all-domains, SSH-key, subscription or invoice views).
- The reseller account is shared across all end customers, so account-wide data
  must never reach a client area.
- Custom actions are registered only as per-service buttons; the `rdns` and
  `backup` actions re-check that the referenced resource belongs to the service.
- There is intentionally no separate admin UI in the extension: lifecycle
  (create/suspend/unsuspend/terminate/upgrade) is driven by Paymenter's native
  admin service screen, which calls the extension hooks.

## Layout

```
extensions/Servers/Ignite/
  Ignite.php                     Server extension (Paymenter\Extensions\Servers\Ignite)
  resources/views/*.blade.php    Per-service views (overview, rdns, backups, mail, plesk)
  resources/lang/{de,en}/        Translations
```

## Requirements

- Paymenter 1.x (Laravel 12, PHP 8.3+)
- An Ignite account + API key (scopes `read`, `write`, `services.read`,
  `services.write`)

## Install

1. Copy `extensions/Servers/Ignite` into your Paymenter `extensions/Servers/`
   directory.

2. Make the shared core and the official SDK available to Paymenter. Add them to
   the Paymenter root `composer.json` (path repositories for local development):

   ```json
   {
     "repositories": [
       { "type": "path", "url": "../public-api/php" },
       { "type": "path", "url": "../reslling-addons/core" }
     ],
     "require": {
       "ignite/api-client": "*",
       "ignite/reseller-core": "*"
     }
   }
   ```

   Then run `composer update ignite/api-client ignite/reseller-core` in the
   Paymenter root. For a deployment, point the repositories at the published
   packages instead.

3. In the Paymenter admin create a **Server** with the Ignite extension and fill
   in the API base URL and key. Use **Test connection**.

4. On a **Product**, pick the Ignite server, then map the API product, billing
   cycle, payment method, coupon and (optionally) template/location/SSH key.

## How it works

- Provisioning/suspend/unsuspend/terminate/upgrade are implemented as the
  optional Paymenter server hooks, triggered by Paymenter's native admin and
  order flow.
- Service management uses Paymenter's `getActions()` (`button`, `view`, `text`)
  so the actions and tabs appear on the service page, scoped to that service.
- The remote Ignite service id is stored in the Paymenter service `properties`
  under `ignite_service_id`.

## Billing

Ordering uses the API-capable `POST /checkout` with a non-interactive payment
method (`guthaben`, `businessfund` or `invoice`) plus coupon (price reduction)
and an `Idempotency-Key`. Payment is settled server-side and the service is only
provisioned once payment is settled; a retry returns the stored result instead
of ordering twice. Account-wide billing (BusinessFund vs. collective invoice,
invoices, vouchers) is managed by the reseller directly in their Ignite account;
it is not exposed to end customers.
