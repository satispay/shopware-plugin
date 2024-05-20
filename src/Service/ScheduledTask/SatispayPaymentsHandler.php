<?php declare(strict_types=1);

namespace Satispay\Service\ScheduledTask;

use Satispay\Exception\SatispaySettingsInvalidException;
use Satispay\Helper\Order;
use Satispay\Helper\Finalize;
use Satispay\Helper\PaymentWrapperApi;
use Satispay\System\Config as SatispayConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Psr\Log\LoggerInterface;
use Satispay\Validation\SatispayConfiguration;
use Satispay\Controller\Api\ConfigurationController;

class SatispayPaymentsHandler extends ScheduledTaskHandler
{
    /**
     * @var SatispayConfig
     */
    protected $config;
    /**
     * @var SatispayConfiguration
     */
    protected $configValidation;
    /**
     * @var Order
     */
    protected $orderHelper;
    /**
     * @var Finalize
     */
    protected $finalizeHelper;
    /**
     * @var EntityRepository
     */
    protected EntityRepository $scheduledTaskRepository;
    /**
     * @var EntityRepository
     */
    protected $salesChannelRepository;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * SatispayPaymentsHandler constructor.
     */
    public function __construct(
        SatispayConfig $config,
        SatispayConfiguration $configValidation,
        Order $orderHelper,
        Finalize $finalizeHelper,
        EntityRepository $scheduledTaskRepository,
        EntityRepository $salesChannelRepository,
        LoggerInterface $logger
    )
    {
        $this->config = $config;
        $this->configValidation = $configValidation;
        $this->orderHelper = $orderHelper;
        $this->finalizeHelper = $finalizeHelper;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->logger = $logger;
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public static function getHandledMessages(): iterable
    {
        return [ SatispayPayments::class ];
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelIds = $this->getSalesChannelsForCriteria($context);
        if (empty($salesChannelIds)) {
            return;
        }
        foreach ($salesChannelIds as $salesChannelId) {
            $satispayOrders = $this->orderHelper->getSatispayOrders($context, $salesChannelId);
            $this->processSatispayOrders($satispayOrders, $context);
        }
    }

    private function getSalesChannelsForCriteria($context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('typeId', ConfigurationController::STOREFRONT_SALESCHANNEL_TYPE_ID));
        $salesChannels = $this->salesChannelRepository->search($criteria, $context);
        $salesChannelsIds = [];
        foreach ($salesChannels as $salesChannel) {
            try {
                $salesChannelId = $salesChannel->getId();
                if ($this->configValidation->isSatispayActivatedCorrectly($salesChannelId) &&
                    $this->config->isScheduledTaskEnabled($salesChannelId)) {
                    // satispay is available in the channel and scheduled task is activated
                    $salesChannelsIds[] = $salesChannelId;
                }
            } catch (SatispaySettingsInvalidException $e) {
                $this->logger->debug('Sales channel is not configured with Satispay: '. $salesChannelId);
            }
        }
        return $salesChannelsIds;
    }

    /**
     * Method that processes satispay orders
     *
     * @param $orders
     */
    private function processSatispayOrders($orders, $context)
    {
        foreach ($orders as $order) {
            $satispayPaymentId = null;
            $transactionCollection = $order->getTransactions();

            if ($transactionCollection === null) {
                $this->logger->debug('Could not get satispay transaction for order with id '. $order->getId());
                continue;
            }

            $transaction = $transactionCollection->last();

            if ($transaction === null) {
                $this->logger->debug('Could not get satispay transaction for order with id '. $order->getId());
                continue;
            }

            //check if paymentId is set for transaction
            $customFields = $transaction->getCustomFields();
            if (is_null($customFields)) {
                continue;
            }
            foreach ($customFields as $customFieldTag => $customFieldData) {
                if ($customFieldTag === PaymentWrapperApi::PAYMENT_ID_IN_TRANSACTION_CUSTOM_FIELD) {
                    $satispayPaymentId = $customFieldData;
                }
            }
            if ($satispayPaymentId != null) {
                $this->finalizeSatispayTransaction($transaction, $satispayPaymentId, $order, $context);
            }
        }
    }

    /**
     * Method that finalize the transaction for the specified transaction/order
     *
     * @param $transaction
     * @param $satispayPaymentId
     * @param $order
     * @param $context
     */
    private function finalizeSatispayTransaction($transaction, $satispayPaymentId, $order, $context) {

        $salesChannelId = $order->getSalesChannelId();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $salesChannelId));
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->first();
        try {
            $this->finalizeHelper->finalizeTransaction($transaction, $satispayPaymentId, $salesChannel, $context);
        } catch (\Exception $e) {
            $this->logger->debug("Could not finalize Satispay Transaction: " . $satispayPaymentId);
        }
    }
}
