<?php
declare(strict_types=1);

namespace MageBooster\Odeal\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

/**
 * Ödeal virtual POS API: bearer token, a pay-by-link 3D Secure page per order, check-status and refunds.
 * Card details are entered on Ödeal's page only.
 */
class Gateway
{
    /** @var array<int, string> */
    private array $tokens = [];

    public function __construct(
        private Settings $settings,
        private Http $http,
        private ReturnUrl $returnUrl,
        private DebugLog $log
    ) {
    }

    public function start(Order $order, array $charge): string
    {
        $payment = $order->getPayment();
        if ($payment->getAdditionalInformation('pay_url')) {
            return (string)$payment->getAdditionalInformation('pay_url');
        }
        $incrementId = (string)$order->getIncrementId();
        $billing = $order->getBillingAddress();
        $data = $this->call($order, '/vpos/pay-by-link', [
            'amount' => $charge['amount'],
            'installment' => 1,
            'externalId' => $incrementId,
            'returnUrl' => $this->returnUrl->build('mbodeal/payment/callback', $incrementId),
            'phone' => $billing ? preg_replace('/\D/', '', (string)$billing->getTelephone()) : '',
            'currency' => $charge['currency'],
            'buyerName' => $billing ? trim($billing->getFirstname() . ' ' . $billing->getLastname()) : '',
            'buyerCity' => $billing ? (string)$billing->getCity() : '',
            'buyerMail' => (string)$order->getCustomerEmail(),
            'buyerAddress' => $billing ? implode(' ', (array)$billing->getStreet()) : '',
        ]);
        $result = $this->unwrap($data);
        $url = (string)($result['checkout3DUrl'] ?? '');
        if ($url === '') {
            $this->log->error('pay-by-link refused', ['order' => $incrementId, 'answer' => $data]);
            throw new LocalizedException(__('Ödeal could not open the payment page. Please try again.'));
        }
        $payment->setAdditionalInformation('odeal_id', (string)($result['id'] ?? ''));
        $payment->setAdditionalInformation('pay_url', $url);
        return $url;
    }

    /**
     * @return array{state: string, txn?: string, reason?: string, extra?: array}
     */
    public function status(Order $order): array
    {
        $data = $this->call($order, '/vpos/check-status', ['externalId' => (string)$order->getIncrementId()]);
        $result = $this->unwrap($data);
        $status = strtoupper((string)($result['payment_status'] ?? $result['paymentStatus'] ?? ''));
        if ($status === 'COMPLETED') {
            $details = (array)($result['order'] ?? []);
            return ['state' => 'paid',
                'txn' => (string)($details['rrn'] ?? $order->getPayment()->getAdditionalInformation('odeal_id') ?: $order->getIncrementId()),
                'extra' => array_filter(['odeal_rrn' => $details['rrn'] ?? null, 'odeal_auth_code' => $details['auth_code'] ?? null])];
        }
        if (in_array($status, ['FAILED', 'CANCELLED'], true)) {
            return ['state' => 'failed', 'reason' => $status];
        }
        return ['state' => 'pending'];
    }

    public function refund(InfoInterface $payment, float $amount): string
    {
        $order = $payment->getOrder();
        $path = '/' . ltrim($this->settings->value('refund_path', (int)$order->getStoreId()) ?: 'vpos/refund', '/');
        $data = $this->call($order, $path, [
            'id' => (string)$payment->getAdditionalInformation('odeal_id'),
            'externalId' => (string)$order->getIncrementId(),
            'amount' => $amount,
        ]);
        $result = $this->unwrap($data);
        if (($data['_http_status'] ?? 0) >= 300 || !empty($data['error']) || (isset($data['success']) && !$data['success'])) {
            throw new LocalizedException(__('Ödeal refused the refund: %1', (string)($data['message'] ?? $result['message'] ?? '?')));
        }
        return (string)($result['id'] ?? $result['refundId'] ?? '');
    }

    private function call(Order $order, string $path, array $body): array
    {
        $storeId = (int)$order->getStoreId();
        $data = $this->http->postJson($this->settings->baseUrl($storeId) . $path, $body,
            ['Authorization' => 'Bearer ' . $this->token($storeId)]);
        if (($data['_http_status'] ?? 0) === 401) {
            unset($this->tokens[$storeId]);
            throw new LocalizedException(__('Ödeal rejected the access token. Please check the credentials.'));
        }
        return $data;
    }

    /** A new token invalidates the previous one, so one token per request is fetched and reused. */
    private function token(int $storeId): string
    {
        if (isset($this->tokens[$storeId])) {
            return $this->tokens[$storeId];
        }
        $clientId = $this->settings->value('client_id', $storeId);
        $clientSecret = $this->settings->secret('client_secret', $storeId);
        if ($clientId === '' || $clientSecret === '') {
            throw new LocalizedException(__('Ödeal credentials are not set.'));
        }
        $data = $this->http->postJson($this->settings->baseUrl($storeId) . '/' . ltrim($this->settings->value('token_path', $storeId) ?: 'token', '/'), [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'grantType' => $this->settings->value('grant_type', $storeId) ?: 'client_credentials',
            'scope' => $this->settings->value('scope', $storeId),
        ]);
        $result = $this->unwrap($data);
        $token = (string)($result['accessToken'] ?? $result['access_token'] ?? $result['token'] ?? '');
        if ($token === '') {
            throw new LocalizedException(__('Ödeal did not return an access token. Please check the credentials.'));
        }
        return $this->tokens[$storeId] = $token;
    }

    private function unwrap(array $data): array
    {
        foreach (['result', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key] + $data;
            }
        }
        return $data;
    }
}
