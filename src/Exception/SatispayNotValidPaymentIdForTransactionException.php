<?php declare(strict_types=1);

namespace Satispay\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class SatispayNotValidPaymentIdForTransactionException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'SATISPAY_PLUGIN_NOT_VALID_PAYMENT_ID_FOR_TRANSACTION_EXCEPTION';
    }
}
