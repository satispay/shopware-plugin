<?php

declare(strict_types=1);

namespace Satispay\System;

use Satispay\Exception\SatispayMissingConfigException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Config
{
    /**
     * @var SystemConfigService
     */
    protected $systemConfig;

    public function __construct(
        SystemConfigService $systemConfig
    ) {
        $this->systemConfig = $systemConfig;
    }

    public function isSandBox(?string $salesChannelId = null): bool
    {
        $sandbox = $this->systemConfig->get('Satispay.config.sandBox', $salesChannelId);
        $isSandbox = false;
        if ($sandbox && $sandbox === true) {
            $isSandbox = true;
        }

        return $isSandbox;
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

        if (!$value) {
            throw new SatispayMissingConfigException('Activation code is missing!');
        }

        return $value;
    }

    public function getConfigKeyForActivationCode(string $activationCode, ?string $salesChannelId = null): string
    {
        return 'Satispay.' . $activationCode . '::' . $this->getType($salesChannelId);
    }

    /**
     * Save values in system config
     */
    public function saveActivationValues(string $publicKey, string $privateKey, string $keyId, ?string $salesChannelId = null): void
    {
        $type = $this->getType($salesChannelId);

        $this->systemConfig->set("Satispay.config.{$type}publicKey", $publicKey, $salesChannelId);
        $this->systemConfig->set("Satispay.config.{$type}privateKey", $privateKey, $salesChannelId);
        $this->systemConfig->set("Satispay.config.{$type}keyId", $keyId, $salesChannelId);
    }

    /**
     * @return array|mixed|null
     */
    public function getContextsWhereActivationCodeIsUsed(string $activationCode, ?string $salesChannelId = null)
    {
        $key = $this->getConfigKeyForActivationCode($activationCode, $salesChannelId);

        return $this->systemConfig->get($key);
    }

    public function updateContextsWhereActivationCodeIsUsed(?string $toSalesChannelId): void
    {
        $activationCode = $this->getActivationCode($toSalesChannelId);

        $activated = $this->getContextsWhereActivationCodeIsUsed($activationCode, $toSalesChannelId);

        $activatedSalesChannel = $activated ? json_decode($activated, true) : [];

        //update activated value
        $activatedSalesChannel[] = $toSalesChannelId;
        //update 'Satispay.'.$key
        $this->systemConfig->set(
            $this->getConfigKeyForActivationCode($activationCode, $toSalesChannelId),
            json_encode($activatedSalesChannel)
        );
    }

    protected function getType(?string $salesChannelId = null): string
    {
        return $this->isSandBox($salesChannelId) ? 'sandbox' : 'live';
    }
}
