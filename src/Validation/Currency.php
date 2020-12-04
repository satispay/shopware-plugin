<?php
declare(strict_types=1);

namespace Satispay\Validation;

use Satispay\Exception\SatispayCurrencyException;
use Satispay\Helper\Currency as CurrencyHelper;
use Shopware\Core\Framework\Context;

class Currency
{
    /**
     * @var CurrencyHelper
     */
    private $currencyHelper;

    public function __construct(
        CurrencyHelper $currencyHelper
    ) {
        $this->currencyHelper = $currencyHelper;
    }

    /**
     * @throws SatispayCurrencyException
     */
    public function validateCurrencyId(string $currencyId, Context $context): void
    {
        $currency = $this->currencyHelper->getCurrencyById($currencyId, $context);

        // block satispay if order is not in euro
        if (strcmp($currency->getIsoCode(), 'EUR') !== 0) {
            throw new SatispayCurrencyException('Satispay cannot execute payment with currency different from euro!');
        }
    }
}
