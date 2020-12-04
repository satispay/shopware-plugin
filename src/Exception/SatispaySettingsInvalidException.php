<?php declare(strict_types=1);

namespace Satispay\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class SatispaySettingsInvalidException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'SATISPAY_PLUGIN__SETTING_EXCEPTION';
    }
}
