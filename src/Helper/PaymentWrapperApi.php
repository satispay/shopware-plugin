<?php /** @noinspection PhpDeprecationInspection */

declare(strict_types=1);

namespace Satispay\Helper;

use PackageVersions\Versions;
use Satispay\Exception\SatispayPaymentIdInTransactionEmptyException;
use Satispay\System\Config as SatispayConfig;
use SatispayGBusiness\Api as SatispayApi;
use SatispayGBusiness\ApiAuthentication;
use SatispayGBusiness\Payment as SatispayPaymentApi;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use function sprintf;

class PaymentWrapperApi
{
    public const ACCEPTED_STATUS = 'ACCEPTED';
    public const PENDING_STATUS = 'PENDING';
    public const CANCELLED_STATUS = 'CANCELED';

    public const PAYMENT_ID_IN_TRANSACTION_CUSTOM_FIELD = 'satispay_payment_id';

    public const PAYMENT_URL_PRODUCTION = 'https://online.satispay.com/pay/';
    public const PAYMENT_URL_SANDBOX = 'https://staging.online.satispay.com/pay/';

    /**
     * @var SatispayConfig
     */
    protected $config;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Currency
     */
    private $currencyHelper;

    public function __construct(
        SatispayConfig $config,
        RouterInterface $router,
        Currency $currencyHelper
    ) {
        $this->config = $config;
        $this->router = $router;
        $this->currencyHelper = $currencyHelper;
    }

    public function sendPayloadToSatispay(string $salesChannelId, array $payload): \stdClass
    {
        $this->initSatispay($salesChannelId);

        return SatispayPaymentApi::create($payload);
    }

    public function getPaymentStatusOnSatispay(string $salesChannelId, string $paymentId): \stdClass
    {
        $this->initSatispay($salesChannelId);

        return SatispayPaymentApi::get($paymentId);
    }

    /**
     * @param $payment
     */
    public function generateRedirectPaymentUrl(string $salesChannelId, $payment): string
    {
        $satispayUrl = self::PAYMENT_URL_PRODUCTION . $payment->id;
        if ($this->config->isSandBox($salesChannelId)) {
            $satispayUrl = self::PAYMENT_URL_SANDBOX . $payment->id;
        }

        return $satispayUrl;
    }

    public function createPaymentPayload(AsyncPaymentTransactionStruct $transaction, Context $context): array
    {
        $orderTransaction = $transaction->getOrderTransaction();
        $orderTransactionAmount = $orderTransaction->getAmount();
        $order = $transaction->getOrder();
        $currency = $this->currencyHelper->getCurrencyById($order->getCurrencyId(), $context);

        $redirectUrl = $transaction->getReturnUrl();

        //The url that will be called with an http GET request when the Payment changes state.
        // When url is called a Get payment details can be called to know the new Payment status.
        // Note that {uuid} will be replaced with the Payment ID

        $callback_url = $this->router->generate(
            'frontend.satispay.paymentUpdated',
            [
                'transaction_id' => $transaction->getOrderTransaction()->getId(),
                'payment_id' => '', //it is not possible put {uuid} here because it will be escaped
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $callback_url = sprintf('%s%s', $callback_url, '{uuid}');

        $redirectUrl = sprintf('%s&%s', $redirectUrl, 'payment_id={uuid}');

        return [
            'flow' => 'MATCH_CODE',
            'amount_unit' => $orderTransactionAmount->getTotalPrice() * 100,
            'currency' => $currency->getIsoCode(),
            'description' => '#' . $order->getOrderNumber(),
            'callback_url' => $callback_url,
            'external_code' => $order->getOrderNumber(),
            'metadata' => [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'redirect_url' => $redirectUrl,
                'transaction_id' => $transaction->getOrderTransaction()->getId(),
            ],
        ];
    }

    public function getAuthenticationBySatispay(?string $salesChannelId): ApiAuthentication
    {
        $activationCode = $this->config->getActivationCode($salesChannelId);

        if ($this->config->isSandBox($salesChannelId)) {
            SatispayApi::setSandbox(true);
        }

        return SatispayApi::authenticateWithToken($activationCode);
    }

    public function createRefundPayloadByPaymentId(OrderEntity $order, string $paymentId, float $amount, Context $context): array
    {
        $currency = $this->currencyHelper->getCurrencyById($order->getCurrencyId(), $context);

        return [
            'flow' => 'REFUND',
            'amount_unit' => $amount,
            'currency' => $currency->getIsoCode(),
            'parent_payment_uid' => $paymentId,
            'external_code' => $order->getOrderNumber(),
        ];
    }

    /**
     * @throws SatispayPaymentIdInTransactionEmptyException
     */
    public function getPaymentIdFromTransaction(OrderTransactionEntity $transaction): string
    {
        $customFields = $transaction->getCustomFields();

        if (!is_array($customFields)
            || !array_key_exists(self::PAYMENT_ID_IN_TRANSACTION_CUSTOM_FIELD, $customFields)
        ) {
            throw new SatispayPaymentIdInTransactionEmptyException(
                'Satispay Payment id is missing in transaction_id ' . $transaction->getId()
            );
        }

        return $customFields[self::PAYMENT_ID_IN_TRANSACTION_CUSTOM_FIELD];
    }

    protected function initSatispay(?string $salesChannelId = null): void
    {
        SatispayApi::setPlatformHeader('Shopware');
        SatispayApi::setPlatformVersionHeader(Versions::getVersion('shopware/platform'));
        SatispayApi::setPluginNameHeader('shopware-plugin');
        SatispayApi::setPluginVersionHeader(Versions::getVersion('satispay/shopware6-plugin'));
        SatispayApi::setTypeHeader('ECOMMERCE-PLUGIN');
        SatispayApi::setTrackingHeader('shopware_b324105f-8712');

        SatispayApi::setSandbox($this->config->isSandBox($salesChannelId));

        //get keys for that channel
        $publicKey = $this->config->getPublicKey($salesChannelId);
        $privateKey = $this->config->getPrivateKey($salesChannelId);
        $keyId = $this->config->getKeyId($salesChannelId);

        SatispayApi::setPublicKey($publicKey);
        SatispayApi::setPrivateKey($privateKey);
        SatispayApi::setKeyId($keyId);
    }
}
