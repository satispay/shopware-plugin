<?php declare(strict_types=1);

namespace Satispay\Helper;

use Satispay\Exception\SatispayInvalidAuthorizationException;
use Satispay\Helper\PaymentWrapperApi;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Psr\Log\LoggerInterface;

class Finalize
{
    /**
     * @var PaymentWrapperApi
     */
    protected $paymentWrapperApi;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var OrderTransactionStateHandler
     */
    protected $orderTransactionStateHandler;

    /**
     * Finalize constructor.
     * @param \Satispay\Helper\PaymentWrapperApi $paymentWrapperApi
     * @param LoggerInterface $logger
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     */
    public function __construct(
        PaymentWrapperApi $paymentWrapperApi,
        LoggerInterface $logger,
        OrderTransactionStateHandler $orderTransactionStateHandler
    ) {
        $this->paymentWrapperApi = $paymentWrapperApi;
        $this->logger = $logger;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
    }

    /**
     * Finalize a transaction after calling Satispay APIs for transaction status
     *
     * @param $transaction
     * @param $paymentId
     * @param $salesChannel
     * @param $context
     */
    public function finalizeTransaction($transaction, $paymentId, $salesChannel, $context)
    {
        try {
            $satispayPayment = $this->paymentWrapperApi->getPaymentStatusOnSatispay($salesChannel->getId(), $paymentId);
        } catch (\Exception $e) {
            $this->logger->error(
                $e->getMessage(),
                $e->getTrace()
            );

            throw new SatispayInvalidAuthorizationException($e->getMessage());
        }
        $transactionId = $transaction->getId();

        if ($satispayPayment->status === PaymentWrapperApi::ACCEPTED_STATUS) {
            $this->logger->debug('Transaction ' . $transactionId . ' is payed');
            // retrocompatibility with 6.1
            if (method_exists($this->orderTransactionStateHandler, 'paid')
                && is_callable([$this->orderTransactionStateHandler, 'paid'])) {
                $this->orderTransactionStateHandler->paid($transactionId, $context);
            } else {
                $this->orderTransactionStateHandler->pay($transactionId, $context);
            }
        } elseif ($satispayPayment->status === PaymentWrapperApi::CANCELLED_STATUS) {
            $this->logger->debug('Transaction ' . $transactionId . ' is cancelled');
            $this->orderTransactionStateHandler->cancel($transactionId, $context);
        } else {
            //the payment is still in pending, the status should be PENDING
            $this->logger->debug('Transaction ' . $transactionId . ' is still in pending on satispay');
        }
    }
}
