<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Billing surface for the reseller account.
 *
 * Both platform billing modes are covered:
 *  - Invoice mode: the business account collects all services into one monthly
 *    invoice on the configured anchor day (`/business/billing`).
 *  - BusinessFund: a commitment/prepaid contract that services draw from
 *    (`/business/fund`).
 *
 * Price reduction is applied through coupons (`/coupons/preview`, and
 * `couponCode` at order time) and NetPoints redemption.
 */
final class BillingManager
{
    public function __construct(private readonly Api $api)
    {
    }

    /** @param array<string, scalar|null> $query @return array<string, mixed> */
    public function invoices(array $query = []): array
    {
        return $this->api->get('/invoices', $query);
    }

    /** @return array<string, mixed> */
    public function invoice(string $id): array
    {
        return $this->api->get('/invoices/' . rawurlencode($id));
    }

    /**
     * @param array{paymentMethod?: string, savedMandateId?: string, amountFromBalance?: float, netPointsRedeemEur?: float} $body
     * @return array<string, mixed>
     */
    public function payInvoice(string $id, array $body = []): array
    {
        return $this->api->post('/invoices/' . rawurlencode($id) . '/pay', $body);
    }

    /** @return array<string, mixed> */
    public function disputeInvoice(string $id, string $reason): array
    {
        return $this->api->post('/invoices/' . rawurlencode($id) . '/dispute', ['reason' => $reason]);
    }

    /** @return array<string, mixed> */
    public function withdrawInvoice(string $id): array
    {
        return $this->api->post('/invoices/' . rawurlencode($id) . '/withdraw', []);
    }

    public function invoiceDocumentUrl(string $id): string
    {
        return rtrim($this->api->config()->apiBaseUrl, '/') . '/invoices/' . rawurlencode($id) . '/document';
    }

    public function creditNoteDocumentUrl(string $id): string
    {
        return rtrim($this->api->config()->apiBaseUrl, '/') . '/invoices/' . rawurlencode($id) . '/credit-note-document';
    }

    /** @return array<string, mixed> */
    public function paymentMethods(): array
    {
        return $this->api->get('/account/payment-methods');
    }

    /** @return array<string, mixed> */
    public function couponPreview(array $body): array
    {
        return $this->api->post('/coupons/preview', $body);
    }

    /** @return array<string, mixed> */
    public function netPoints(): array
    {
        return $this->api->get('/netpoints');
    }

    /** @return array<string, mixed> */
    public function netPointsPreview(float $orderTotal): array
    {
        return $this->api->post('/netpoints/preview', ['orderTotal' => $orderTotal]);
    }

    // --- Business fund (prepaid commitment) ---

    /** @return array<string, mixed> */
    public function fund(): array
    {
        return $this->api->get('/business/fund');
    }

    /** @return array<string, mixed> */
    public function requestFund(float $amount, int $bindingMonths): array
    {
        return $this->api->post('/business/fund', ['amount' => $amount, 'bindingMonths' => $bindingMonths]);
    }

    /** @return array<string, mixed> */
    public function acceptFund(string $offerId, string $paymentMethod = 'balance'): array
    {
        return $this->api->post('/business/fund/' . rawurlencode($offerId) . '/accept', ['paymentMethod' => $paymentMethod]);
    }

    /** @return array<string, mixed> */
    public function cancelFund(string $contractId): array
    {
        return $this->api->post('/business/fund/' . rawurlencode($contractId) . '/cancel', []);
    }

    /** @return array<string, mixed> */
    public function rejectFund(string $offerId, ?string $reason = null): array
    {
        return $this->api->post('/business/fund/' . rawurlencode($offerId) . '/reject', array_filter(['reason' => $reason], static fn ($v) => $v !== null));
    }

    /** @return array<string, mixed> */
    public function specialTermination(): array
    {
        return $this->api->get('/business/fund/special-termination');
    }

    /** @return array<string, mixed> */
    public function requestSpecialTermination(string $contractId, string $reason): array
    {
        return $this->api->post('/business/fund/special-termination', ['contractId' => $contractId, 'reason' => $reason]);
    }

    // --- Invoice billing mode ---

    /** @return array<string, mixed> */
    public function businessBilling(): array
    {
        return $this->api->get('/business/billing');
    }

    /** @return array<string, mixed> */
    public function enableInvoiceBilling(?int $anchorDay = null): array
    {
        return $this->api->post('/business/billing', array_filter(['anchorDay' => $anchorDay], static fn ($v) => $v !== null));
    }

    /** @return array<string, mixed> */
    public function disableInvoiceBilling(): array
    {
        return $this->api->delete('/business/billing');
    }

    /** @return array<string, mixed> */
    public function businessEligibility(): array
    {
        return $this->api->get('/business/eligibility');
    }

    // --- Wallet / vouchers ---

    /** @return array<string, mixed> */
    public function vouchers(): array
    {
        return $this->api->get('/wallet/vouchers');
    }

    /** @return array<string, mixed> */
    public function redeemVoucher(string $code): array
    {
        return $this->api->post('/wallet/vouchers/redeem', ['code' => $code]);
    }
}
