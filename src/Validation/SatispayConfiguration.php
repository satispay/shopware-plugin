<?php
declare(strict_types=1);

namespace Satispay\Validation;

use Satispay\Exception\SatispaySettingsInvalidException;
use Satispay\System\Config;

class SatispayConfiguration
{
    /**
     * @var Config
     */
    protected $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * @throws SatispaySettingsInvalidException
     */
    public function isSatispayActivatedCorrectly(?string $salesChannelId = null): bool
    {
        if (
            !$this->config->getPublicKey($salesChannelId)
            || !$this->config->getPrivateKey($salesChannelId)
            || !$this->config->getKeyId($salesChannelId)
        ) {
            throw new SatispaySettingsInvalidException(
                'Satispay gateway not configured!'
            );
        }

        return true;
    }
}
