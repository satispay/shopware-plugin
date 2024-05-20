<?php declare(strict_types=1);

namespace Satispay\Helper;

use Satispay\Handler\PaymentHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Satispay\System\Config as SatispayConfig;

class Order
{
    /**
     * @var EntityRepository
     */
    protected $orderRepository;
    /**
     * @var EntityRepository
     */
    protected $paymentRepository;
    /**
     * @var SatispayConfig
     */
    protected $config;

    /**
     * Order constructor.
     * @param SatispayConfig $config
     * @param EntityRepository $orderRepository
     * @param EntityRepository $paymentRepository
     */
    public function __construct(
        SatispayConfig $config,
        EntityRepository $orderRepository,
        EntityRepository $paymentRepository
    ) {
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * Return the Satispay orders following a complex criteria rule
     *
     * @param $context
     * @param $salesChannelId
     * @return array|EntityCollection
     * @throws \Exception
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
        return $this->orderRepository->search($criteriaOrder, $context)->getEntities();
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
        return $this->paymentRepository->searchIds($paymentCriteria, $context)->firstId();
    }

    /**
     * Get the start criteria for the scheduled datetime
     *
     * @return string
     * @throws \Exception
     */
    private function getStartDateScheduledTime($salesChannelId)
    {
        $now = new \DateTime();
        $scheduledTimeFrame = $this->config->getTimeFrameForScheduledTask($salesChannelId);
        $tosub = new \DateInterval('PT'. $scheduledTimeFrame . 'H');
        return $now->sub($tosub)->format('Y-m-d H:i:s');
    }

    /**
     * Get the end criteria for the scheduled datetime
     *
     * @return string
     * @throws \Exception
     */
    private function getEndDateScheduledTime()
    {
        $now = new \DateTime();
        // remove just 1 hour so normal transactions can still be processed
        $tosub = new \DateInterval('PT'. 1 . 'H');
        return $now->sub($tosub)->format('Y-m-d H:i:s');
    }
}
