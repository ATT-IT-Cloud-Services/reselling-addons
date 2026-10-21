# Ignite reselling addons

Reselling integrations for the **Ignite** hosting platform, for both common
billing platforms:

| Folder | Platform | Language |
| --- | --- | --- |
| [`whmcs/`](whmcs/README.md) | WHMCS 8.x | PHP |
| [`paymenter/`](paymenter/README.md) | Paymenter 1.x | PHP |
| [`core/`](core/) | Shared, platform-agnostic | PHP library |

Everything is deliberately a **plain reselling addon**. It sells and manages:

- **Proxmox** virtual machines
- **Plesk** hosting
- **Pterodactyl** game/app containers
- **Mailcow** mail services
- **Domains** (registrar registrations and DNS zones managed by the platform)

It intentionally does **not** expose containers/vRoute/game-server extras,
BYOIP, floating IPs, storage, teams, families or affiliate tooling.

## What "plain reselling" means here

The reselling company is an **Ignite customer, not a platform administrator**.
It runs its own WHMCS or Paymenter, and that interface is where it manages
everything. Two audiences, two scopes — this separation is mandatory:

- **Reseller admin** (the reseller's own WHMCS admin / Paymenter admin): sees
  the whole reseller Ignite account — all services, the catalog mapping, and an
  admin link that opens a service in the reseller's own Ignite customer panel.
- **End customer** (WHMCS/Paymenter client area): sees and manages **only the
  one service** that was ordered. Never account-wide lists.

The reseller account is shared across all end customers, so account-wide data
(all services, all domains, all SSH keys, subscriptions, invoices) must never
reach a client area — that would leak other customers' resources. Provisioning
actions are always executed server-side with the reseller's API key and
re-checked against the mapped service.

The addon never creates end-customer logins or per-service permission grants on
Ignite; access control stays in WHMCS/Paymenter.

## Features

### Per service (end-customer client area)

| Capability | API used |
| --- | --- |
| Service overview, status, power actions | `GET /services/{id}`, `POST /services/{id}/action` |
| Reverse DNS (PTR) for the service IPs | `/ip-manager` (filtered to the service) |
| DNS records for the service's own domain | `/domains/{id}/records` (matched to `domainName`) |
| Mail service | `/services/{id}/mail/{domains,mailboxes,aliases}` |
| Plesk | `/services/{id}/plesk/{setup,credentials,domains}` |
| Backups | `/services/{id}/backups` |

### Account-wide (reseller admin only)

| Capability | API used |
| --- | --- |
| Catalog sync (categories + products) | `GET /products`, `/products/{category}`, `/products/{category}/{product}` |
| Provision (with coupon / price reduction) | `POST /checkout` (non-interactive method + `Idempotency-Key`) |
| Suspend / resume / terminate | `POST /services/{id}/action`, `POST /services/{id}/subscription/cancel`, `DELETE /services/{id}` |
| Renew / interval / autopay / extend | `/services/{id}/subscription/*`, `/services/{id}/renew` |
| Change package / resource preview | `/services/{id}/upgrade`, `/services/{id}/upgrade-preview` |
| SSH keys (account level, attached at provision time) | `/ssh-keys`, `/services/{id}/settings` |
| Billing mode: invoice vs. BusinessFund | `/business/billing`, `/business/fund` |
| Invoices / payments / vouchers | `/invoices`, `/invoices/{id}/pay`, `/wallet/vouchers` |
| Price reduction preview | `/coupons/preview`, `couponCode` at order time |
| Link into the reseller's Ignite panel | customer frontend URL only |

## Configuration (all platforms)

The **API base URL is not hardcoded**. Every module takes:

| Setting | Meaning | Example |
| --- | --- | --- |
| API base URL | Host-root API URL | `https://api.<platform-domain>` |
| API key | Ignite API key, `ek_…` | — |
| Web app base URL | Reseller customer frontend, admin links only | `https://www.<platform-domain>` |
| Language | Catalog + UI language | `de` / `en` |
| Catalog cache TTL | Seconds | `900` |

There is deliberately **no admin-ui / admin-backend URL**: the reseller has no
platform admin access, so such links would lead nowhere useful.

The API key needs the scopes `read`, `write`, `services.read` and
`services.write`.

## Architecture

`core/` holds the platform-agnostic reselling layer (namespace
`IgniteReseller\`). It wraps the official SDK's typed client and its HTTP
transport in one `Api`, then builds managers:

`Catalog`, `ServiceManager`, `SubscriptionManager`, `DomainManager`,
`ReverseDnsManager`, `SshKeyManager`, `BillingManager`, `MailManager`,
`PleskManager`, `BackupManager`, plus `Links` for the customer-frontend link and
`ErrorTranslator` for code-to-text mapping.

Whichever platform is used, the route layer only talks to this core. Adding a
third platform means writing a new thin adapter, not touching the API logic.

## Install

- WHMCS: see [`whmcs/README.md`](whmcs/README.md)
- Paymenter: see [`paymenter/README.md`](paymenter/README.md)

Both consume `public-api/php` (the official SDK) and `core/` through Composer
path repositories while developing in this monorepo; for deployment, point the
repositories at the published packages.

## Known constraints (by platform design)

- Ordering uses the API-capable `POST /checkout` with a non-interactive
  payment method (`guthaben`, `businessfund` or `invoice`) and an
  `Idempotency-Key`, so payment is settled server-side and a retry cannot order
  or charge twice. Redirect-based methods and package upgrades cannot be
  executed with the API key.
- There is no API to create sub-users or per-service permissions, so the addon
  does not offer permission management. Access control is the billing
  platform's job, and account-wide resources are never shown to end customers.
- Reverse DNS (PTR) is part of the service management page itself: each service
  lists its own IP entries from the IP manager (`/ip-manager`) and the customer
  edits the PTR directly, scoped to that service.
- DNS record management in the client area is limited to the service's own
  domain. Account-wide zone management belongs to the reseller admin.
- Bringing a foreign domain onto the platform nameservers is intentionally not
  exposed. The addons manage DNS records and rDNS for domains that are already
  on the account; they do not offer BYO-domain zone creation or deletion.
