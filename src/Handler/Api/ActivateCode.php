<?php
declare(strict_types=1);

namespace Satispay\Handler\Api;

use Psr\Log\LoggerInterface;
use Satispay\Helper\Activation;
use Satispay\Helper\PaymentWrapperApi;
use Satispay\System\Config as SatispayConfig;

class ActivateCode
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var SatispayConfig
     */
    protected $satispayConfig;

    /**
     * @var Activation
     */
    protected $activationHelper;

    /**
     * @var PaymentWrapperApi
     */
    protected $paymentWrapperApi;

    public function __construct(
        PaymentWrapperApi $paymentWrapperApi,
        SatispayConfig $satispayConfig,
        Activation $activationHelper,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->satispayConfig = $satispayConfig;
        $this->activationHelper = $activationHelper;
        $this->paymentWrapperApi = $paymentWrapperApi;
    }

    /**
     * Activate satispay code for a sales channel
     *
     * @throws \Exception
     */
    public function activateChannel(?string $salesChannelId = null): void
    {
        $this->logger->debug(
            'Activating Satispay channel',
            [
                'sales channel id' => empty($salesChannelId) ? 'NULL' : $salesChannelId,
            ]
        );

        if ($this->activationHelper->isActivationCodeAlreadyUsed($salesChannelId)) {
            //the activation for the sales channel in input was already executed
            $this->logger->debug('Satispay activation code already used! The activation values will be copied');

            //save the value for the new sales channel
            $this->activationHelper->copyActivationValuesToSalesChannel($salesChannelId);

            return;
        }

        $this->logger->debug(
            'Starting new activation with Satispay API',
            [
                'salesChannel' => $salesChannelId,
                'activationCode' => $this->satispayConfig->getActivationCode($salesChannelId),
            ]
        );

        try {
            $authentication = $this->paymentWrapperApi->getAuthenticationBySatispay($salesChannelId);
            $this->satispayConfig->saveActivationValues(
                $authentication->publicKey,
                $authentication->privateKey,
                $authentication->keyId,
                $salesChannelId
            );
            $this->satispayConfig->updateContextsWhereActivationCodeIsUsed($salesChannelId);
        } catch (\Exception $e) {
            $this->logger->error(self::class . ' ' . $e->getMessage(), $e->getTrace());

            throw $e;
        }
    }
}
