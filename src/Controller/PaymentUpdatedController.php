<?php

declare(strict_types=1);

namespace Satispay\Controller;

use Psr\Log\LoggerInterface;
use Satispay\Exception\SatispayInvalidAuthorizationException;
use Satispay\Exception\SatispayNotValidPaymentIdForTransactionException;
use Satispay\Exception\SatispayPaymentIdInTransactionEmptyException;
use Satispay\Handler\Api\UpdateTransaction;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class PaymentUpdatedController extends StorefrontController
{
    public const PAYMENT_ID = 'payment_id';
    public const TRANSACTION_ID = 'transaction_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var UpdateTransaction
     */
    private $updateTransaction;

    public function __construct(
        UpdateTransaction $updateTransaction,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->updateTransaction = $updateTransaction;
    }

    /**
     * @Route("/satispay/payment/update", name="frontend.satispay.paymentUpdated", options={"seo"="false"}, methods={"GET"})
     */
    public function execute(Request $request, SalesChannelContext $context): Response
    {
        $paymentId = $request->get(self::PAYMENT_ID);
        $transactionId = $request->get(self::TRANSACTION_ID);

        if (!$paymentId || !$transactionId) {
            $this->logger->error(self::class . ' Missing request parameters');

            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->updateTransaction->execute($transactionId, $paymentId, $context);
        } catch (SatispayInvalidAuthorizationException $e) {
            $this->logger->error(self::class . ' Satispay plugin not configured correctly');

            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        } catch (SatispayNotValidPaymentIdForTransactionException $e) {
            $this->logger->error(
                self::class . ' Payment and Transaction are not related',
                [
                    self::TRANSACTION_ID => $transactionId,
                    self::PAYMENT_ID => $paymentId,
                ]
            );

            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        } catch (SatispayPaymentIdInTransactionEmptyException $e) {
            //in this case the payment id is missing in the transaction entity and the
            //status of the response is not cancel
            $this->logger->error(
                self::class . ' Payment in Transaction is missing',
                [
                    self::TRANSACTION_ID => $transactionId,
                    self::PAYMENT_ID => $paymentId,
                ]
            );

            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error(
                self::class . ' ' . $e->getMessage(),
                $e->getTrace()
            );

            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
