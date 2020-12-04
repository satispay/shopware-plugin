<?php

declare(strict_types=1);

namespace Satispay\Helper;

use Satispay\Exception\SatispayCurrencyException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;

class Currency
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $currencyRepository;

    protected $currencyList = [];

    public function __construct(
        EntityRepositoryInterface $currencyRepository
    ) {
        $this->currencyRepository = $currencyRepository;
    }

    /**
     * @throws SatispayCurrencyException
     */
    public function getCurrencyById(string $currencyId, Context $context): CurrencyEntity
    {
        if (!array_key_exists($currencyId, $this->currencyList)) {
            $currency = $this->currencyRepository
                ->search(new Criteria([$currencyId]), $context)
                ->first();

            if (!$currency) {
                throw new SatispayCurrencyException('Currency not found!');
            }
            $this->currencyList[$currencyId] = $currency;
        }

        return $this->currencyList[$currencyId];
    }
}
