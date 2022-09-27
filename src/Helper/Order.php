<?php declare(strict_types=1);

namespace Satispay\Helper;

use Satispay\Handler\PaymentHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Satispay\System\Config as SatispayConfig;

class Order
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var EntityRepositoryInterface
     */
    protected $paymentRepository;
    /**
     * @var SatispayConfig
     */
    protected $config;

    /**
     * Order constructor.
     * @param EntityRepositoryInterface $orderRepository
     * @param EntityRepositoryInterface $paymentRepository
     */
    public function __construct(
        SatispayConfig $config,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $paymentRepository
    ) {
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * Return the Satispay orders following a complex criteria rule
     *
     * @param $context
     * @return array|\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection
     */
    public function getSatispayOrders($context, $salesChannelId)
    {
        $satispayPaymentId = $this->getSatispayPaymentId($context);
        if ($satispayPaymentId == null) {
            return [];
        }
        $rangeStart = $this->getStartDateScheduledTime($salesChannelId);
        $rangeEnd = $this->getEndDateScheduledTime();
        // create a complex criteria condition
        $criteriaOrder = $this->createOrdersCriteria($rangeStart, $rangeEnd, $satispayPaymentId, $salesChannelId);
        $orders = $this->orderRepository->search($criteriaOrder, $context)->getEntities();
        return $orders;
    }

    private function createOrdersCriteria($rangeStart, $rangeEnd, $satispayPaymentId, $salesChannelId)
    {
        $criteriaOrder = new Criteria();
        $criteriaOrder->addAssociation('transactions');
        $criteriaOrder
            ->addFilter(new RangeFilter('order.transactions.createdAt.date',
                    [RangeFilter::GTE => $rangeStart, RangeFilter::LTE => $rangeEnd])
            )
            ->addFilter(new EqualsFilter('order.transactions.paymentMethodId', $satispayPaymentId))
            //take every order in both state open and processing, for version < and > of 6.1
            ->addFilter(new EqualsAnyFilter(
                'order.transactions.stateMachineState.technicalName',
                [\Shopware\Core\Checkout\Order\OrderStates::STATE_OPEN,
                    \Shopware\Core\Checkout\Order\OrderStates::STATE_IN_PROGRESS]
            ))
            ->addFilter(new EqualsFilter('order.salesChannelId', $salesChannelId))
            ->addSorting(new FieldSorting('order.orderNumber'))
            ->addSorting(new FieldSorting('order.transactions.createdAt.date'));
        return $criteriaOrder;
    }

    /**
     * Get the Satispay payment method Id
     *
     * @return string|null
     */
    private function getSatispayPaymentId($context)
    {
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class));
        $satispayPaymentId = $this->paymentRepository->searchIds($paymentCriteria, $context)->firstId();
        return $satispayPaymentId;
    }

    /**
     * Get the start criteria for the scheduled datetime
     *
     * @return string
     */
    private function getStartDateScheduledTime($salesChannelId)
    {
        $now = new \DateTime();
        $nowStart = $now->format('Y-m-d H:i:s');
        $scheduledTimeFrame = $this->config->getTimeFrameForScheduledTask($salesChannelId);
        $tosub = new \DateInterval('PT'. $scheduledTimeFrame . 'H');
        $beforeStart = $now->sub($tosub)->format('Y-m-d H:i:s');
        return $beforeStart;
    }

    /**
     * Get the end criteria for the scheduled datetime
     *
     * @return string
     */
    private function getEndDateScheduledTime()
    {
        $now = new \DateTime();
        $nowStart = $now->format('Y-m-d H:i:s');
        // remove just 1 hour so normal transactions can still be processed
        $tosub = new \DateInterval('PT'. 1 . 'H');
        $endStart = $now->sub($tosub)->format('Y-m-d H:i:s');
        return $endStart;
    }
}
