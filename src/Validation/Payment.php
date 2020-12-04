<?php
declare(strict_types=1);

namespace Satispay\Validation;

use Satispay\Exception\SatispayNotValidPaymentIdForTransactionException;
use Satispay\Exception\SatispayPaymentIdInTransactionEmptyException;
use Satispay\Helper\PaymentWrapperApi;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class Payment
{
    /**
     * @var PaymentWrapperApi
     */
    protected $paymentWrapperApi;

    public function __construct(
        PaymentWrapperApi $paymentWrapperApi
    ) {
        $this->paymentWrapperApi = $paymentWrapperApi;
    }

    /**
     * @throws SatispayNotValidPaymentIdForTransactionException
     * @throws SatispayPaymentIdInTransactionEmptyException
     */
    public function isPaymentIdValidForTransactionId(OrderTransactionEntity $transaction, string $paymentId): void
    {
        $paymentIdFromTransaction = $this->paymentWrapperApi->getPaymentIdFromTransaction($transaction);

        if (
            strcmp($paymentIdFromTransaction, $paymentId) !== 0
        ) {
            throw new SatispayNotValidPaymentIdForTransactionException(
                'Satispay Payment id ' . $transaction->getId()
                . ' is not valid to update transaction id ' . $transaction->getId()
            );
        }
    }
}
