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
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PaymentHandler implements AsynchronousPaymentHandlerInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OrderTransactionStateHandler
     */
    protected $orderTransactionStateHandler;

    /**
     * @var PayTransaction
     */
    private $payTransactionHelper;

    /**
     * @var FinalizeTransaction
     */
    private $finalizeTransaction;

    public function __construct(
        OrderTransactionStateHandler $orderTransactionStateHandler,
        PayTransaction $payTransactionHelper,
        FinalizeTransaction $finalizeTransaction,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->payTransactionHelper = $payTransactionHelper;
        $this->finalizeTransaction = $finalizeTransaction;
    }

    /**
     * {@inheritdoc}
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        try {
            //create payment
            $satispayUrl = $this->payTransactionHelper->getSatispayUrlForOrder($transaction, $salesChannelContext);
        } catch (SatispaySettingsInvalidException $e) {
            $order = $transaction->getOrder();

            $this->logger->error(
                self::class . ' ' . $e->getMessage(),
                [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                ]
            );
            //block the execution with AsyncPaymentProcessException
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'Satispay gateway not configured!'
            );
        } catch (SatispayCurrencyException $e) {
            $order = $transaction->getOrder();

            $this->logger->error(
                self::class . ' ' . $e->getMessage(),
                [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                    'currency id' => $order->getCurrencyId(),
                ]
            );

            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'Currency not valid!'
            );
        } catch (\Exception $e) {
            $order = $transaction->getOrder();

            $this->logger->error(
                self::class . ' ' . $e->getMessage(),
                [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                ]
            );

            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'It is not possible pay with Satispay payment gateway'
            );
        }

        return new RedirectResponse($satispayUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        $paymentId = $request->get('payment_id');

        if (!$paymentId) {
            $this->logger->error(
                self::class . ' Missing payment id in satispay response',
                [
                    'order_id' => $transaction->getOrder()->getId(),
                    'order_number' => $transaction->getOrder()->getOrderNumber(),
                ]
            );

            throw new AsyncPaymentFinalizeException(
                $transaction->getOrderTransaction()->getId(),
                'Missing payment id in satispay response'
            );
        }

        try {
            $this->finalizeTransaction->execute($transaction, $paymentId, $salesChannelContext);
            if ($transaction->getOrderTransaction()->getStateMachineState() === null) {
                throw new AsyncPaymentFinalizeException(
                    $transaction->getOrderTransaction()->getId(),
                    'Missing state machine'
                );
            }

            $paymentStatus = $transaction->getOrderTransaction()->getStateMachineState()->getTechnicalName();
            if ($paymentStatus === OrderTransactionStates::STATE_OPEN) {
                $this->orderTransactionStateHandler->process(
                    $transaction->getOrderTransaction()->getId(),
                    $salesChannelContext->getContext()
                );
            }
        } catch (SatispayPaymentUnacceptedException $e) {
            $this->logger->error(
                self::class . ' Satispay payment not accepted',
                [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ]
            );

            throw new CustomerCanceledAsyncPaymentException($transaction->getOrderTransaction()->getId());
        } catch (SatispayInvalidAuthorizationException $e) {
            $this->logger->error(
                self::class . ' Satispay not correctly configured',
                [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ]
            );

            throw new AsyncPaymentFinalizeException(
                $transaction->getOrderTransaction()->getId(),
                'Satispay not correctly configured'
            );
        }
    }
}
