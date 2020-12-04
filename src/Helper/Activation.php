<?php

declare(strict_types=1);

namespace Satispay\Helper;

use Psr\Log\LoggerInterface;
use Satispay\Exception\SatispaySettingsInvalidException;
use Satispay\System\Config as SatispayConfig;
use Satispay\Validation\SatispayConfiguration;

class Activation
{
    /**
     * @var SatispayConfig
     */
    private $satispayConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SatispayConfiguration
     */
    private $configValidation;

    public function __construct(
        SatispayConfig $satispayConfig,
        SatispayConfiguration $configValidation,
        LoggerInterface $logger
    ) {
        $this->satispayConfig = $satispayConfig;
        $this->logger = $logger;
        $this->configValidation = $configValidation;
    }

    public function isActivationCodeAlreadyUsed(?string $salesChannelId = null): bool
    {
        $activationCode = $this->satispayConfig->getActivationCode($salesChannelId);

        $activated = $this->satispayConfig->getContextsWhereActivationCodeIsUsed($activationCode, $salesChannelId);

        return $activated ? true : false;
    }

    public function copyActivationValuesToSalesChannel(?string $salesChannelId = null): void
    {
        $activationCode = $this->satispayConfig->getActivationCode($salesChannelId);

        $activated = $this->satispayConfig->getContextsWhereActivationCodeIsUsed($activationCode, $salesChannelId);

        $activatedSalesChannel = json_decode($activated, true);

        if (in_array($salesChannelId, $activatedSalesChannel, true)) {
            $this->logger->debug(
                'Channel not be copy because it is already activated!',
                [
                    'sales_channel_to_activate' => $salesChannelId,
                ]
            );
            //in this case the config is already saved for the sales channel
            return;
        }

        //get channel where the keys with $key is saved
        $firstSalesChannelActivated = $activatedSalesChannel[0];

        $this->logger->debug(
            'Copying activated value from config!',
            [
                'key' => $this->satispayConfig->getConfigKeyForActivationCode($activationCode, $salesChannelId),
                'sales_channel_to_activate' => $salesChannelId,
                'activated_sales_channel' => $firstSalesChannelActivated,
            ]
        );

        $this->copyActivationValueFromSalesChannelToSalesChannel($firstSalesChannelActivated, $salesChannelId);
    }

    protected function copyActivationValueFromSalesChannelToSalesChannel(?string $fromSalesChannelId, ?string $toSalesChannelId): void
    {
        try {
            $this->configValidation->isSatispayActivatedCorrectly($fromSalesChannelId);
        } catch (SatispaySettingsInvalidException $e) {
            $this->logger->error(
                'Channel active does not have the config required!',
                [
                    'activated_sales_channel' => $fromSalesChannelId,
                    'sales_channel_to_activate' => $toSalesChannelId,
                ]
            );

            throw $e;
        }

        //save keys in $salesChannelId
        $this->satispayConfig->saveActivationValues(
            $this->satispayConfig->getPublicKey($fromSalesChannelId),
            $this->satispayConfig->getPrivateKey($fromSalesChannelId),
            $this->satispayConfig->getKeyId($fromSalesChannelId),
            $toSalesChannelId
        );

        $this->satispayConfig->updateContextsWhereActivationCodeIsUsed($toSalesChannelId);
    }
}
