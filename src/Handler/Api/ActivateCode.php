<?php
declare(strict_types=1);

namespace Satispay\Handler\Api;

use Psr\Log\LoggerInterface;
use Satispay\Exception\SatispayMissingConfigException;
use Satispay\Exception\SatispaySettingsInvalidException;
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
        try {
            $this->logger->debug(
                'Activating Satispay channel',
                [
                    'sales channel id ' => empty($salesChannelId) ? 'GLOBAL' : $salesChannelId,
                ]
            );

            $this->validateActivationCode($salesChannelId);
            $this->logger->debug(
                'Starting new activation with Satispay API',
                [
                    'salesChannel' => empty($salesChannelId) ? 'GLOBAL' : $salesChannelId,
                    'activationCode' => $this->satispayConfig->getActivationCode($salesChannelId),
                ]
            );
            $authentication = $this->paymentWrapperApi->getAuthenticationBySatispay($salesChannelId);
            $this->satispayConfig->saveActivationValues(
                $authentication->publicKey,
                $authentication->privateKey,
                $authentication->keyId,
                $salesChannelId
            );
        } catch (SatispaySettingsInvalidException $exception) {
            $this->logger->warning($exception->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(self::class . ' ' . $e->getMessage(), $e->getTrace());

            throw $e;
        }
    }

    /**
     * @throws SatispaySettingsInvalidException
     */
    private function isEmptyActivationCode(?string $salesChannelId): void
    {
        try {
            $activationCode = $this->satispayConfig->getActivationCode($salesChannelId);
        } catch (SatispayMissingConfigException $exception) {
            //the activation code is empty which means to reset and delete old values.
            $this->satispayConfig->deleteActivationValues($salesChannelId);
            $errorMessage = 'Empty activation code, delete satispay activation values for salesChannel '
                . (empty($salesChannelId) ? 'GLOBAL' : $salesChannelId);

            throw new SatispaySettingsInvalidException($errorMessage);
        }

    }

    /**
     * @throws SatispaySettingsInvalidException
     */
    private function validateActivationCode(?string $salesChannelId): void
    {
        $this->isEmptyActivationCode($salesChannelId);
        $activationCode = $this->satispayConfig->getActivationCode($salesChannelId);
        if ($salesChannelId !== null) {
            $activatedCodeGlobal = $this->satispayConfig->getGlobalActivatedCodeForSalesChannel($salesChannelId);
            if (!empty($activatedCodeGlobal) && $activationCode == $activatedCodeGlobal) {
                $this->satispayConfig->deleteActivationValues($salesChannelId);
                $errorMessage = 'Activation code already active on global saleschannel.
                Inherit activation values from global.';
                throw new SatispaySettingsInvalidException($errorMessage);
            }
        }
        $activatedCode = $this->satispayConfig->getActivatedCode($salesChannelId);
        if ($activationCode == $activatedCode) {
            $errorMessage = 'Activation code already active for salesChannel '
                . (empty($salesChannelId) ? 'GLOBAL' : $salesChannelId);

            throw new SatispaySettingsInvalidException($errorMessage);
        }
        $salesChannelIdByActivatedCode = $this->satispayConfig->getSalesChannelIdByActivatedCode(
            $activationCode,
            $salesChannelId
        );
        if ($salesChannelIdByActivatedCode !== null) {
            $this->activationHelper->copyActivationValueFromSalesChannelToSalesChannel(
                $salesChannelIdByActivatedCode,
                $salesChannelId
            );
            $errorMessage = 'Activation code already active on another saleschannel.
                Copied activation values from saleschannel ' . $salesChannelIdByActivatedCode;

            throw new SatispaySettingsInvalidException($errorMessage);
        }
    }
}
