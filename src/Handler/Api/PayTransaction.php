<?php

declare(strict_types=1);

namespace Satispay\Handler\Api;

use Psr\Log\LoggerInterface;
use Satispay\Exception\SatispayCurrencyException;
use Satispay\Exception\SatispaySettingsInvalidException;
use Satispay\Helper\PaymentWrapperApi;
use Satispay\Validation\Currency;
use Satispay\Validation\SatispayConfiguration;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayTransaction
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PaymentWrapperApi
     */
    protected $paymentWrapperApi;

    /**
     * @var Currency
     */
    protected $currencyValidation;

    /**
     * @var SatispayConfiguration
     */
    protected $configValidation;

    public function __construct(
        PaymentWrapperApi $paymentWrapperApi,
        SatispayConfiguration $configValidation,
        Currency $currencyValidation,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->paymentWrapperApi = $paymentWrapperApi;
        $this->currencyValidation = $currencyValidation;
        $this->configValidation = $configValidation;
    }

    /**
     * @throws SatispaySettingsInvalidException
     * @throws SatispayCurrencyException
     */
    public function getSatispayUrlForOrder(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext
    ): string {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        //check satispay requirements
        $this->configValidation->isSatispayActivatedCorrectly($salesChannelId);
        $this->currencyValidation->validateCurrencyId($transaction->getOrder()->getCurrencyId(), $salesChannelContext->getContext());

        $paymentBody = $this->paymentWrapperApi->createPaymentPayload($transaction, $salesChannelContext->getContext());

        $this->logger->debug(self::class . ' Created payment request payload', $paymentBody);

        $payment = $this->paymentWrapperApi->sendPayloadToSatispay($salesChannelId, $paymentBody);

        return $this->paymentWrapperApi->generateRedirectPaymentUrl($salesChannelId, $payment);
    }
}
