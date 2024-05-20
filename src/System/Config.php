<?php

declare(strict_types=1);

namespace Satispay\System;

use Doctrine\DBAL\Connection;
use Satispay\Exception\SatispayMissingConfigException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Config
{
    /**
     * @var SystemConfigService
     */
    protected $systemConfig;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        SystemConfigService $systemConfig,
        Connection $connection
    ) {
        $this->systemConfig = $systemConfig;
        $this->connection = $connection;
    }

    public function isSandBox(?string $salesChannelId = null): bool
    {
        $sandbox = $this->systemConfig->get('Satispay.config.sandbox', $salesChannelId);
        $isSandbox = false;
        if ($sandbox && $sandbox === true) {
            $isSandbox = true;
        }

        return $isSandbox;
    }

    /**
     * Check if the Scheduled Task option is enabled
     *
     * @param string|null $salesChannelId
     * @return bool
     */
    public function isScheduledTaskEnabled(?string $salesChannelId = null): bool
    {
        $scheduledTask = $this->systemConfig->get('Satispay.config.scheduledTask', $salesChannelId);
        $isScheduledTask = false;
        if ($scheduledTask && $scheduledTask === true) {
            $isScheduledTask = true;
        }

        return $isScheduledTask;
    }

    /**
     * Get time frame for scheduled task
     *
     * @param string|null $salesChannelId
     * @return bool
     */
    public function getTimeFrameForScheduledTask(?string $salesChannelId = null): int
    {
        $timeFrame = $this->systemConfig->get('Satispay.config.timeFrame', $salesChannelId);
        return $timeFrame;
    }

    public function getPublicKey(?string $salesChannelId = null): ?string
    {
        $type = $this->getType($salesChannelId);

        return $this->systemConfig->get("Satispay.config.{$type}publicKey", $salesChannelId);
    }

    public function getPrivateKey(?string $salesChannelId = null): ?string
    {
        $type = $this->getType($salesChannelId);

        return $this->systemConfig->get("Satispay.config.{$type}privateKey", $salesChannelId);
    }

    public function getKeyId(?string $salesChannelId = null): ?string
    {
        $type = $this->getType($salesChannelId);

        return $this->systemConfig->get("Satispay.config.{$type}keyId", $salesChannelId);
    }

    /**
     * Get the activation code based on the configuration type(sandbox|live)
     *
     * @throws SatispayMissingConfigException
     *
     * @return string activation code
     */
    public function getActivationCode(?string $salesChannelId = null): string
    {
        $type = $this->getType($salesChannelId);
        $value = $this->systemConfig->get("Satispay.config.{$type}ActivationCode", $salesChannelId);

        if (empty($value) || trim($value) == "") {
            throw new SatispayMissingConfigException('Activation code is missing!');
        }

        return $value;
    }

    /**
     * Get the currently actived code based on the configuration type(sandbox|live)
     *
     * @return string|null activation code
     */
    public function getActivatedCode(?string $salesChannelId = null): ?string
    {
        $type = $this->getType($salesChannelId);
        $value = $this->systemConfig->get("Satispay.config.{$type}ActivatedCode", $salesChannelId);

        return $value;
    }

    /**
     * Get the currently actived code fallback global value for the salesChannel in input
     *
     * @return string|null activation code
     */
    public function getGlobalActivatedCodeForSalesChannel(string $salesChannelId): ?string
    {
        $type = $this->getType($salesChannelId);
        $value = $this->systemConfig->get("Satispay.config.{$type}ActivatedCode");

        return $value;
    }

    /**
     * Get the current activation code fallback global value for the salesChannel in input
     *
     * @return string|null activation code
     */
    public function getGlobalActivationCodeForSalesChannel(string $salesChannelId): ?string
    {
        $type = $this->getType($salesChannelId);

        return $this->systemConfig->get("Satispay.config.{$type}ActivationCode");
    }

    /**
     * Save values in system config
     */
    public function saveActivationValues(
        string $publicKey,
        string $privateKey,
        string $keyId,
        ?string $salesChannelId = null
    ): void
    {
        $type = $this->getType($salesChannelId);
        $activateCode = $this->getActivationCode($salesChannelId);
        $this->systemConfig->set("Satispay.config.{$type}publicKey", $publicKey, $salesChannelId);
        $this->systemConfig->set("Satispay.config.{$type}privateKey", $privateKey, $salesChannelId);
        $this->systemConfig->set("Satispay.config.{$type}keyId", $keyId, $salesChannelId);
        $this->systemConfig->set("Satispay.config.{$type}ActivatedCode", $activateCode, $salesChannelId);
    }

    /**
     * @param string|null $salesChannelId
     * removes current activation values
     */
    public function deleteActivationValues(?string $salesChannelId = null): void
    {
        $type = $this->getType($salesChannelId);
        $this->systemConfig->delete("Satispay.config.{$type}publicKey", $salesChannelId);
        $this->systemConfig->delete("Satispay.config.{$type}privateKey", $salesChannelId);
        $this->systemConfig->delete("Satispay.config.{$type}keyId", $salesChannelId);
        $activationCode = $this->systemConfig->get("Satispay.config.{$type}ActivationCode", $salesChannelId);
        if ($salesChannelId == null) {
            $this->systemConfig->delete("Satispay.config.{$type}ActivatedCode", $salesChannelId);
        } else {
            if ($activationCode!= null && trim($activationCode) == "") {
                $activatedCodeGlobal = $this->getGlobalActivatedCodeForSalesChannel($salesChannelId);
                if (empty($activatedCodeGlobal) || trim($activatedCodeGlobal) == "") {
                    $this->systemConfig->delete("Satispay.config.{$type}ActivatedCode", $salesChannelId);
                    $this->systemConfig->delete("Satispay.config.{$type}ActivationCode", $salesChannelId);
                } else {
                    $this->systemConfig->set("Satispay.config.{$type}ActivatedCode", $activationCode, $salesChannelId);
                }
            } else {
                $this->systemConfig->delete("Satispay.config.{$type}ActivatedCode", $salesChannelId);
                $activationCodeGlobal = $this->getGlobalActivationCodeForSalesChannel($salesChannelId);
                $trimActivationCode = isset($activationCode) ? trim($activationCode): false;
                $trimActivationCodeGlobal = isset($activationCodeGlobal) ? trim($activationCodeGlobal): false;
                if ($trimActivationCode == $trimActivationCodeGlobal) {
                    $this->systemConfig->delete("Satispay.config.{$type}ActivationCode", $salesChannelId);
                }
            }
        }
    }

    /**
     * @return string|null
     */
    public function getSalesChannelIdByActivatedCode(string $activationCode, ?string $salesChannelId = null)
    {
        $type = $this->getType($salesChannelId);
        $configurationKey = "Satispay.config.{$type}ActivatedCode";
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('sales_channel_id', 'configuration_value')
            ->from('system_config')
            ->where('configuration_key = :configurationKey')
            ->andWhere('sales_channel_id IS NOT NULL')
            ->orderBy('configuration_key', 'ASC')
            ->addOrderBy('sales_channel_id', 'ASC')
            ->setParameter('configurationKey', $configurationKey);
        $activatedSalesChannel = $queryBuilder->execute()->fetchAll();
        if ($activatedSalesChannel === false) {
            return null;
        }
        foreach ($activatedSalesChannel as $salesChannel) {
            $activatedCode = json_decode($salesChannel['configuration_value'], true)['_value'];
            if ($activatedCode === $activationCode) {
                return Uuid::fromBytesToHex($salesChannel['sales_channel_id']);
            }
        }

        return null;
    }

    protected function getType(?string $salesChannelId = null): string
    {
        return $this->isSandBox($salesChannelId) ? 'sandbox' : 'live';
    }
}
