# WHMCS reselling addon for Ignite

Two WHMCS modules that let a reselling company sell and manage Ignite services
from WHMCS, using the official `ignite/api-client` PHP SDK:

| Module | Path | Audience | Job |
| --- | --- | --- | --- |
| Provisioning server module | `modules/servers/ignite/` | End customer | Maps a WHMCS product to an Ignite product, provisions/suspends/terminates the service, renders the per-service management page |
| Addon module | `modules/addons/ignite_reseller/` | Reseller admin | Connection settings, catalog browser + mapping help, account-wide service overview with links into the reseller's own Ignite panel |

Scope is deliberately plain reselling: **Proxmox, Plesk, Pterodactyl, Mailcow and
domains**. Containers, vRoute, game-server extras and similar are not exposed.

## Audience and scope (important)

The reseller is an **Ignite customer, not a platform administrator**. Their
admin interface is WHMCS itself, and that is where they manage their Ignite
account. Therefore:

- The addon module has **no client-area page**. Account-wide data (all services,
  domains, SSH keys, subscriptions, invoices) is admin-only and never shown to
  end customers.
- The server module's client area is strictly **per service**: overview, power,
  reverse DNS for the service IPs, DNS for the service's own domain, mail,
  Plesk, backups. No account-wide lists.
- All client-area actions run server-side with the reseller's API key and are
  re-checked against the mapped service, so a crafted POST cannot touch another
  customer's resources.
- Admin links point at the reseller's **Ignite customer panel**, never at a
  platform admin UI.

## Requirements

- WHMCS 8.x (PHP 8.2+)
- An Ignite account with an API key. The key needs the scopes
  `read` + `write` + `services.read` + `services.write`.
- Composer, to install the SDK and the shared reseller core.

## Install

1. Copy the module folders into your WHMCS root:

   ```
   modules/servers/ignite/              <- from whmcs/modules/servers/ignite
   modules/addons/ignite_reseller/      <- from whmcs/modules/addons/ignite_reseller
   ```

2. Install the dependencies in each module folder:

   ```bash
   cd modules/servers/ignite && composer install --no-dev --optimize-autoloader
   cd ../../../addons/ignite_reseller && composer install --no-dev --optimize-autoloader
   ```

   The `composer.json` files reference `public-api/php` and `reslling-addons/core`
   as path repositories while developing inside the monorepo. For a real
   deployment, point them at the published package repositories instead.

3. In WHMCS, activate **Setup > Addon Modules > Ignite Reseller** and enter the
   API base URL, API key, and (optionally) the Ignite customer frontend URL for
   admin links.

4. Create a WHMCS product, set **Module Settings > Module** to `ignite`, pick the
   API product, billing cycle, payment method and (optionally) a coupon code.

## How services map

WHMCS owns the end customer and its billing. Each WHMCS service maps to exactly
one Ignite service owned by the reseller's Ignite account. The addon never
creates end-customer logins or permissions on Ignite; access control is WHMCS's
job.

Provisioning uses the API-capable `POST /checkout` with a non-interactive
payment method (`guthaben`, `businessfund` or `invoice`) and an
`Idempotency-Key`. Payment is settled server-side and the service is only
provisioned once payment is settled; a retry returns the stored result instead
of ordering twice.
