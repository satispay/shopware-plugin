<?php declare(strict_types=1);

namespace Satispay\Controller\Api;

use Psr\Log\LoggerInterface;
use Satispay\Helper\PaymentWrapperApi;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Log\Package;
use function number_format;
use function round;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('storefront')]
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
     * @var EntityRepository
     */
    protected $orderRepository;

    /**
     * @var OrderTransactionStateHandler
     */
    private $orderTransactionStateHandler;

    public function __construct(
        PaymentWrapperApi $paymentWrapperApi,
        EntityRepository $orderRepository,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        LoggerInterface $logger
    ) {
        $this->paymentWrapperApi = $paymentWrapperApi;
        $this->orderRepository = $orderRepository;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->logger = $logger;
    }

    #[Route(path: '/api/_action/satispay/payment-details/{orderId}/{paymentId}', name: 'api.action.satispay.status', methods: ['GET'])]
    #[Route(path: '/api/v{version}/_action/satispay/payment-details/{orderId}/{paymentId}', name: 'api.action.satispay.staus.version', methods: ['GET'])]
    public function paymentDetails(string $orderId, string $paymentId, Context $context): JsonResponse
    {
        try {
            $salesChannelId = $this->getSalesChannelIdByOrderId($orderId, $context);
            $response = $this->paymentWrapperApi->getPaymentStatusOnSatispay($salesChannelId, $paymentId);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($response);
    }

    #[Route(path: '/api/_action/satispay/refund-payment/{orderId}/{paymentId}', name: 'api.action.satispay.payment-refund', methods: ['POST'])]
    #[Route(path: '/api/v{version}/_action/satispay/refund-payment/{orderId}/{paymentId}', name: 'api.action.satispay.payment-refund.version', methods: ['POST'])]
    /**
     * @throws \Exception
     */
    public function refundPayment(
        Request $request,
        string $orderId,
        string $paymentId,
        Context $context
    ): JsonResponse {

        $amount = (string) $request->request->get('refundAmount');
        if (preg_match('/[,.]/', $amount)) {
            $amount = preg_replace('/(\d+[,.]\d{2}).*/', '$1', $amount); // limit to two digits after comma
        } else {
            $amount .= '.00';
        }
        $amount = preg_replace('/[,.]/', '', $amount); // remove comma or dot
        $amount = (int) $amount; // convert the amount to int (cents)

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
     * @param string $orderId
     * @param Context $context
     * @return string|null
     */
    private function getSalesChannelIdByOrderId(string $orderId, Context $context): ?string
    {
        /** @var OrderEntity $order */
        $order = $this->getOrderById($orderId, $context);

        return $order->getSalesChannelId();
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity|null
     */
    private function getOrderById(string $orderId, Context $context): ?OrderEntity
    {
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();

        if ($order === null) {
            throw OrderException::orderNotFound($orderId);
        }

        return $order;
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return void
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
     * @param string $orderId
     * @param Context $context
     * @return OrderTransactionEntity
     */
    private function getOrderTransaction(string $orderId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions');
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order === null) {
            throw OrderException::orderNotFound($orderId);
        }

        $transactionCollection = $order->getTransactions();

        if ($transactionCollection === null) {
            throw OrderException::orderNotFound($orderId);
        }

        $transaction = $transactionCollection->last();

        if ($transaction === null) {
            throw OrderException::orderNotFound($orderId);
        }

        return $transaction;
    }
}

