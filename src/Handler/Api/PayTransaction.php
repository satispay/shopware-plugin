<?php

declare(strict_types=1);

namespace Satispay\Handler\Api;

use Psr\Log\LoggerInterface;
use Satispay\Exception\SatispayCurrencyException;
use Satispay\Exception\SatispaySettingsInvalidException;
use Satispay\Helper\PaymentWrapperApi;
use Satispay\Validation\Currency;
use Satispay\Validation\SatispayConfiguration;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

#[Package('checkout')]
class PayTransaction
{
    public function __construct(
        private readonly PaymentWrapperApi $paymentWrapperApi,
        private readonly SatispayConfiguration $configValidation,
        private readonly Currency $currencyValidation,
        private readonly LoggerInterface $logger,
        private readonly EntityRepository $orderTransactionRepo
    ) {
    }

    /**
     * @throws SatispaySettingsInvalidException
     * @throws SatispayCurrencyException
     */
    public function getSatispayUrlForOrder(
        OrderTransactionEntity $orderTransaction,
        PaymentTransactionStruct $transaction,
        Context $context
    ): string {
        $order = $orderTransaction->getOrder();
        $salesChannelId = $order->getSalesChannelId();

        //check satispay requirements
        $this->configValidation->isSatispayActivatedCorrectly($salesChannelId);
        $this->currencyValidation->validateCurrencyId($order->getCurrencyId(), $context);

        $paymentBody = $this->paymentWrapperApi->createPaymentPayload($orderTransaction, $transaction, $context);

        $this->logger->debug(self::class . ' Created payment request payload', $paymentBody);

        $payment = $this->paymentWrapperApi->sendPayloadToSatispay($salesChannelId, $paymentBody);

        if (isset($payment->id)) {
            $this->orderTransactionRepo->update([[
                'id' => $transaction->getOrderTransactionId(),
                'customFields' => [
                    PaymentWrapperApi::PAYMENT_ID_IN_TRANSACTION_CUSTOM_FIELD => $payment->id,
                ],
            ]], $context);
        }
            return $this->paymentWrapperApi->generateRedirectPaymentUrl($payment);
    }
}
