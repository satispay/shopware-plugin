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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

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

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderTransactionRepo;

    public function __construct(
        PaymentWrapperApi $paymentWrapperApi,
        SatispayConfiguration $configValidation,
        Currency $currencyValidation,
        LoggerInterface $logger,
        EntityRepositoryInterface $orderTransactionRepo
    ) {
        $this->logger = $logger;
        $this->paymentWrapperApi = $paymentWrapperApi;
        $this->currencyValidation = $currencyValidation;
        $this->configValidation = $configValidation;
        $this->orderTransactionRepo = $orderTransactionRepo;
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

        if (isset($payment->id)) {
            $this->orderTransactionRepo->update([[
                'id' => $transaction->getOrderTransaction()->getId(),
                'customFields' => [
                    PaymentWrapperApi::PAYMENT_ID_IN_TRANSACTION_CUSTOM_FIELD => $payment->id,
                ],
            ]], $salesChannelContext->getContext());
        }
        return $this->paymentWrapperApi->generateRedirectPaymentUrl($payment);
    }
}
