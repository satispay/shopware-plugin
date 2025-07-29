<?php

declare(strict_types=1);

namespace Satispay\Handler;

use Psr\Log\LoggerInterface;
use Satispay\Exception\SatispayCurrencyException;
use Satispay\Exception\SatispayInvalidAuthorizationException;
use Satispay\Exception\SatispayPaymentUnacceptedException;
use Satispay\Exception\SatispaySettingsInvalidException;
use Satispay\Handler\Api\FinalizeTransaction;
use Satispay\Handler\Api\PayTransaction;
use Satispay\Helper\PaymentWrapperApi;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PaymentHandler extends AbstractPaymentHandler
{
     public function __construct(
        private readonly OrderTransactionStateHandler $orderTransactionStateHandler,
        private readonly PayTransaction $payTransactionHelper,
        private readonly FinalizeTransaction $finalizeTransaction,
        private readonly LoggerInterface $logger,
        private readonly PaymentWrapperApi $paymentWrapperApi,
        private readonly EntityRepository $orderTransactionRepository,
     ) {
    }

    private function getOrderTransaction(string $orderTransactionId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.salesChannel');

        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction === null) {
            throw PaymentException::asyncProcessInterrupted(
                $orderTransactionId,
                'Order transaction not found'
            );
        }

        return $orderTransaction;
    }



    /**
     * {@inheritdoc}
     */
    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): ?RedirectResponse {
        try {
            $orderTransaction = $this->getOrderTransaction($transaction->getOrderTransactionId(), $context);

            //create payment
            $satispayUrl = $this->payTransactionHelper->getSatispayUrlForOrder($orderTransaction, $transaction, $context);
        } catch (SatispaySettingsInvalidException $e) {
            $order = $orderTransaction->getOrder();

            $this->logger->error(
                self::class . ' ' . $e->getMessage(),
                [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                ]
            );
            //block the execution with AsyncPaymentProcessException
            throw PaymentException::asyncProcessInterrupted(
                $transaction->getOrderTransactionId(),
                'Satispay gateway not configured!'
            );
        } catch (SatispayCurrencyException $e) {
            $order = $orderTransaction->getOrder();

            $this->logger->error(
                self::class . ' ' . $e->getMessage(),
                [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                    'currency id' => $order->getCurrencyId(),
                ]
            );

            throw PaymentException::asyncProcessInterrupted(
                $transaction->getOrderTransactionId(),
                'Currency not valid!'
            );
        } catch (\Exception $e) {
            $order = $orderTransaction->getOrder();

            $this->logger->error(
                self::class . ' ' . $e->getMessage(),
                [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                ]
            );

            throw PaymentException::asyncProcessInterrupted(
                $transaction->getOrderTransactionId(),
                'It is not possible to pay with Satispay payment gateway'
            );
        }

        return new RedirectResponse($satispayUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function finalize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {

        try {
            $orderTransaction = $this->getOrderTransaction($transaction->getOrderTransactionId(), $context);
            $paymentId = $this->paymentWrapperApi->getPaymentIdFromTransaction($orderTransaction);;
        } catch (\Exception $e) {
            $paymentId = null;
        }

        if (!$paymentId) {
            $this->logger->error(
                self::class . ' Missing payment id in satispay response',
                [
                    'order_id' => $orderTransaction->getOrder()->getId(),
                    'order_number' => $orderTransaction->getOrder()->getOrderNumber(),
                ]
            );

            throw PaymentException::asyncFinalizeInterrupted(
                $transaction->getOrderTransactionId(),
                'Missing payment id in satispay response'
            );
        }

        try {
            $this->finalizeTransaction->execute($orderTransaction, $paymentId);
            $this->orderTransactionStateHandler->process(
                $transaction->getOrderTransactionId(),
                $context
            );
        } catch (SatispayPaymentUnacceptedException $e) {
            $this->logger->error(
                self::class . ' Satispay payment not accepted',
                [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ]
            );

            throw PaymentException::customerCanceled($transaction->getOrderTransactionId(), '');
        } catch (SatispayInvalidAuthorizationException $e) {
            $this->logger->error(
                self::class . ' Satispay not correctly configured',
                [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ]
            );

            throw PaymentException::asyncFinalizeInterrupted(
                $transaction->getOrderTransactionId(),
                'Satispay not correctly configured'
            );
        } catch (\Exception $e) {
            $this->logger->error(
                self::class . ' Satispay - There has been an error paying the order',
                [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ]
            );

            throw PaymentException::asyncFinalizeInterrupted(
                $transaction->getOrderTransactionId(),
                'Satispay - There has been an error paying the order'
            );
        }
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }
}
