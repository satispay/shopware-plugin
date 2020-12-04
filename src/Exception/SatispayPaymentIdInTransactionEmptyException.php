<?php declare(strict_types=1);

namespace Satispay\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class SatispayPaymentIdInTransactionEmptyException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'SATISPAY_PLUGIN_PAYMENT_ID_MISSING_IN_TRANSACTION_EXCEPTION';
    }
}
