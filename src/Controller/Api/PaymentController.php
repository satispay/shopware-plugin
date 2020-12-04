<?php declare(strict_types=1);

namespace Satispay\Controller\Api;

use Psr\Log\LoggerInterface;
use Satispay\Helper\PaymentWrapperApi;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use function number_format;
use function round;

/**
 * @RouteScope(scopes={"api"})
 */
class PaymentController extends AbstractController
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
     * @var EntityRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderTransactionStateHandler
     */
    private $orderTransactionStateHandler;

    public function __construct(
        PaymentWrapperApi $paymentWrapperApi,
        EntityRepositoryInterface $orderRepository,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        LoggerInterface $logger
    ) {
        $this->paymentWrapperApi = $paymentWrapperApi;
        $this->orderRepository = $orderRepository;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->logger = $logger;
    }

    /**
     * @Route("/api/v{version}/_action/satispay/payment-details/{orderId}/{paymentId}", name="api.action.satispay.staus", methods={"GET"})
     */
    public function paymentDetails(string $orderId, string $paymentId, Context $context): JsonResponse
    {
        try {
            $salesChannelId = $this->getSalesChannelIdByOrderId($orderId, $context);
            $response = $this->paymentWrapperApi->getPaymentStatusOnSatispay($salesChannelId, $paymentId);
        } catch (OrderNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/api/v{version}/_action/satispay/refund-payment/{orderId}/{paymentId}", name="api.action.satispay.payment-refund", methods={"POST"})
     *
     * @throws \Exception
     */
    public function refundPayment(
        Request $request,
        string $orderId,
        string $paymentId,
        Context $context
    ): JsonResponse {
        $amount = (float) $request->request->get('refundAmount');
        $amount = round($amount, 2);
        $amount = number_format($amount, 2);
        $amount *= 100;

        try {
            /** @var OrderEntity $order */
            $order = $this->getOrderById($orderId, $context);
            $salesChannelId = $order->getSalesChannelId();

            $payload = $this->paymentWrapperApi->createRefundPayloadByPaymentId($order, $paymentId, $amount, $context);
            $this->logger->debug(self::class . ' Refund payload', $payload);

            $result = $this->paymentWrapperApi->sendPayloadToSatispay($salesChannelId, $payload);
            $this->applyRefundStateToPayment($orderId, $context);
        } catch (\Exception $e) {
            //the exception here should be any problem on Satispay, in the message there is the request id to
            // communicate to the support
            $this->logger->error(
                self::class . ' : ' . $e->getMessage(),
                [
                    'Order id' => $orderId,
                    'Payment id' => $paymentId,
                    'Amount' => $amount,
                ]
            );

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($result);
    }

    /**
     * @throws OrderNotFoundException
     *
     * @return string
     */
    private function getSalesChannelIdByOrderId(string $orderId, Context $context): ?string
    {
        /** @var OrderEntity $order */
        $order = $this->getOrderById($orderId, $context);

        return $order->getSalesChannelId();
    }

    /**
     * @throws OrderNotFoundException
     *
     * @return OrderEntity
     */
    private function getOrderById(string $orderId, Context $context): ?OrderEntity
    {
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();

        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    /**
     * @throws InvalidTransactionException
     * @throws OrderNotFoundException
     * @throws InvalidOrderException
     * @noinspection TypeUnsafeComparisonInspection
     */
    private function applyRefundStateToPayment(string $orderId, Context $context): void
    {
        $transaction = $this->getOrderTransaction($orderId, $context);
        if ($transaction->getStateMachineState() !== null
            && ($transaction->getStateMachineState()->getTechnicalName() === OrderTransactionStates::STATE_PAID
            || $transaction->getStateMachineState()->getTechnicalName() === OrderTransactionStates::STATE_PARTIALLY_PAID)) {
            $this->orderTransactionStateHandler->refund($transaction->getId(), $context);
        }
    }

    /**
     * @throws OrderNotFoundException
     * @throws InvalidOrderException
     */
    private function getOrderTransaction(string $orderId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions');
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        $transactionCollection = $order->getTransactions();

        if ($transactionCollection === null) {
            throw new InvalidOrderException($orderId);
        }

        $transaction = $transactionCollection->last();

        if ($transaction === null) {
            throw new InvalidOrderException($orderId);
        }

        return $transaction;
    }
}
