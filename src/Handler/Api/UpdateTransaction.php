<?php

declare(strict_types=1);

namespace Satispay\Handler\Api;

use Psr\Log\LoggerInterface;
use Satispay\Exception\SatispayInvalidAuthorizationException;
use Satispay\Exception\SatispayNotValidPaymentIdForTransactionException;
use Satispay\Exception\SatispayPaymentIdInTransactionEmptyException;
use Satispay\Helper\PaymentWrapperApi;
use Satispay\Validation\Payment;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UpdateTransaction
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderTransactionRepo;

    /**
     * @var OrderTransactionStateHandler
     */
    protected $orderTransactionStateHandler;

    /**
     * @var PaymentWrapperApi
     */
    protected $paymentWrapperApi;

    /**
     * @var Payment
     */
    protected $paymentValidation;

    public function __construct(
        PaymentWrapperApi $paymentWrapperApi,
        Payment $paymentValidation,
        EntityRepositoryInterface $orderTransactionRepo,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->paymentWrapperApi = $paymentWrapperApi;
        $this->paymentValidation = $paymentValidation;
    }

    /**
     * @throws SatispayInvalidAuthorizationException
     * @throws SatispayNotValidPaymentIdForTransactionException
     */
    public function execute(string $transactionId, string $paymentId, SalesChannelContext $salesChannelContext): void
    {
        $satispayPayment = $this->getPaymentStatusOnSatispay($salesChannelContext->getSalesChannel()->getId(), $paymentId);
        $this->logger->debug('Satispay status payment  ' . $paymentId . ' is ' . $satispayPayment->status);
        $transaction = $this->getTransactionById($transactionId, $salesChannelContext);

        try {
            $this->paymentValidation->isPaymentIdValidForTransactionId($transaction, $paymentId);
        } catch (SatispayPaymentIdInTransactionEmptyException $e) {
            if ($satispayPayment->status === PaymentWrapperApi::CANCELLED_STATUS) {
                //only if the status is cancel and the payment id is not in the custom field of the transaction
                //the transaction will be cancelled
                $this->orderTransactionStateHandler->cancel($transactionId, $salesChannelContext->getContext());

                return;
            }
            $this->logger->error($e->getMessage(), $e->getTrace());

            throw $e;
        }

        if ($satispayPayment->status === PaymentWrapperApi::ACCEPTED_STATUS) {
            $this->logger->debug('Transaction ' . $transactionId . ' is payed');
            $this->orderTransactionStateHandler->paid($transactionId, $salesChannelContext->getContext());
        } elseif ($satispayPayment->status === PaymentWrapperApi::CANCELLED_STATUS) {
            $this->logger->debug('Transaction ' . $transactionId . ' is cancelled');
            $this->orderTransactionStateHandler->cancel($transactionId, $salesChannelContext->getContext());
        } else {
            //the payment is still in pending, the status should be PENDING
            $this->logger->debug('Transaction ' . $transactionId . ' is still in pending on satispay');
        }
    }

    public function getTransactionById(string $transactionId, SalesChannelContext $salesChannelContext): OrderTransactionEntity
    {
        //TODO: check if the version_id is required to fetch the correct row (id and version_id are the primary key)
        $transaction = $this->orderTransactionRepo
            ->search(new Criteria([$transactionId]), $salesChannelContext->getContext())
            ->get($transactionId);
        if (!$transaction) {
            $this->logger->error('Missing transaction with id' . $transactionId);

            throw new InvalidTransactionException($transactionId);
        }

        return $transaction;
    }

    public function getPaymentStatusOnSatispay(string $salesChannelId, string $paymentId): \stdClass
    {
        try {
            $satispayPayment = $this->paymentWrapperApi->getPaymentStatusOnSatispay($salesChannelId, $paymentId);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());

            throw new SatispayInvalidAuthorizationException($e->getMessage());
        }

        return $satispayPayment;
    }
}
