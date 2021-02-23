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

    public function copyActivationValueFromSalesChannelToSalesChannel(string $fromSalesChannelId, ?string $toSalesChannelId): void
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

        $this->satispayConfig->saveActivationValues(
            $this->satispayConfig->getPublicKey($fromSalesChannelId),
            $this->satispayConfig->getPrivateKey($fromSalesChannelId),
            $this->satispayConfig->getKeyId($fromSalesChannelId),
            $toSalesChannelId
        );
    }
}
