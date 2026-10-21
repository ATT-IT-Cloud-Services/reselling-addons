<?php

declare(strict_types=1);

namespace IgniteReseller;

use Ignite\ApiClient\ApiException;

/**
 * The API returns machine-readable error codes, never finished sentences.
 * This resolver turns the codes the reseller flows can actually trigger into
 * German/English copy. Unknown codes fall back to a neutral message plus the
 * raw code so support can still look it up.
 */
final class ErrorTranslator
{
    /** @var array<string, array{de: string, en: string}> */
    private const MESSAGES = [
        'unauthenticated' => ['de' => 'Die API-Verbindung ist nicht angemeldet. Bitte API-Schlüssel prüfen.', 'en' => 'The API connection is not authenticated. Check the API key.'],
        'apiKeyMissingPermission' => ['de' => 'Der API-Schlüssel hat keine Berechtigung für diese Aktion.', 'en' => 'The API key is missing permission for this action.'],
        'apiKeyExpired' => ['de' => 'Der API-Schlüssel ist abgelaufen.', 'en' => 'The API key has expired.'],
        'forbidden' => ['de' => 'Diese Aktion ist nicht erlaubt.', 'en' => 'This action is not permitted.'],
        'notFound' => ['de' => 'Die angeforderte Ressource wurde nicht gefunden.', 'en' => 'The requested resource was not found.'],
        'serviceNotFound' => ['de' => 'Der Service wurde nicht gefunden.', 'en' => 'The service was not found.'],
        'productNotFound' => ['de' => 'Das Produkt wurde nicht gefunden.', 'en' => 'The product was not found.'],
        'providerNotFound3' => ['de' => 'Der Anbieter wurde nicht gefunden.', 'en' => 'The provider was not found.'],
        'selectedProviderUnavailable' => ['de' => 'Der gewählte Anbieter ist derzeit nicht verfügbar.', 'en' => 'The selected provider is currently unavailable.'],
        'billingCycleDaysMustBeOneOfValue' => ['de' => 'Der Abrechnungszeitraum ist für dieses Produkt nicht erlaubt.', 'en' => 'The billing cycle is not allowed for this product.'],
        'invalidSshPublicKeyFormat' => ['de' => 'Das Format des öffentlichen SSH-Schlüssels ist ungültig.', 'en' => 'The SSH public key format is invalid.'],
        'maximumNumberOfSshKeysReachedLimitMax' => ['de' => 'Die maximale Anzahl an SSH-Schlüsseln ist erreicht.', 'en' => 'The maximum number of SSH keys has been reached.'],
        'insufficientBalance' => ['de' => 'Das Guthaben reicht für diese Zahlung nicht aus.', 'en' => 'The balance is not sufficient for this payment.'],
        'insufficientFunds' => ['de' => 'Das Guthaben reicht für diese Zahlung nicht aus.', 'en' => 'The balance is not sufficient for this payment.'],
        'invoiceNotPayable' => ['de' => 'Diese Rechnung kann nicht bezahlt werden.', 'en' => 'This invoice cannot be paid.'],
        'invoiceAlreadyPaid' => ['de' => 'Diese Rechnung ist bereits bezahlt.', 'en' => 'This invoice is already paid.'],
        'sepaIsOnlyAvailableForBalanceTopUp' => ['de' => 'SEPA-Lastschrift ist nur für das Aufladen des Guthabens verfügbar.', 'en' => 'SEPA direct debit is only available for balance top-up.'],
        'clientPlatformNotAllowed' => ['de' => 'Diese Aktion ist für diesen Client-Typ nicht erlaubt.', 'en' => 'This action is not allowed for this client type.'],
        'thisRouteCannotBeAccessedWithAnApiKey' => ['de' => 'Diese Funktion ist mit einem API-Schlüssel nicht verfügbar; bitte die Abo-Schnittstelle verwenden.', 'en' => 'This function is not available with an API key; use the subscription interface instead.'],
        'directUpgradesAreNotSupported' => ['de' => 'Upgrades müssen über den Warenkorb bzw. die Abo-Schnittstelle gebucht werden.', 'en' => 'Upgrades must be booked through the cart/subscription interface.'],
        'couponInvalid' => ['de' => 'Der Gutscheincode ist ungültig.', 'en' => 'The coupon code is invalid.'],
        'couponExpired' => ['de' => 'Der Gutscheincode ist abgelaufen.', 'en' => 'The coupon code has expired.'],
        'couponAlreadyUsed' => ['de' => 'Der Gutscheincode wurde bereits verwendet.', 'en' => 'The coupon code has already been used.'],
        'couponNotApplicable' => ['de' => 'Der Gutscheincode gilt nicht für diese Bestellung.', 'en' => 'The coupon code does not apply to this order.'],
        'domainAlreadyExists' => ['de' => 'Diese Domain ist bereits vorhanden.', 'en' => 'This domain already exists.'],
        'domainReserved' => ['de' => 'Diese Domain ist reserviert.', 'en' => 'This domain is reserved.'],
        'invalidDomain' => ['de' => 'Die Domain ist ungültig.', 'en' => 'The domain is invalid.'],
        'rate_limited' => ['de' => 'Zu viele Anfragen. Bitte kurz warten.', 'en' => 'Too many requests. Please wait a moment.'],
        'rateLimited' => ['de' => 'Zu viele Anfragen. Bitte kurz warten.', 'en' => 'Too many requests. Please wait a moment.'],
        'internalServerError' => ['de' => 'Auf dem Server ist ein Fehler aufgetreten.', 'en' => 'An internal server error occurred.'],
    ];

    public static function code(\Throwable $e): string
    {
        if ($e instanceof ApiException) {
            return $e->errorCode;
        }

        return 'unexpectedError';
    }

    public static function status(\Throwable $e): int
    {
        return $e instanceof ApiException ? $e->status : 0;
    }

    /** @param array<string, mixed> $params */
    public static function message(\Throwable $e, string $locale = 'de', array $params = []): string
    {
        $code = self::code($e);
        $entry = self::MESSAGES[$code] ?? null;
        $lang = $locale === 'en' ? 'en' : 'de';

        $message = $entry[$lang] ?? ($lang === 'en'
            ? 'The action could not be completed.'
            : 'Die Aktion konnte nicht abgeschlossen werden.');

        if ($params !== []) {
            foreach ($params as $key => $value) {
                $message = str_replace('{' . $key . '}', (string) $value, $message);
            }
        }

        return $message;
    }

    /** @return array{code: string, status: int, message: string} */
    public static function describe(\Throwable $e, string $locale = 'de'): array
    {
        return [
            'code' => self::code($e),
            'status' => self::status($e),
            'message' => self::message($e, $locale),
        ];
    }
}
