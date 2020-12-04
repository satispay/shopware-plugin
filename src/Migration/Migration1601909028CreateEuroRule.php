<?php declare(strict_types=1);

namespace Satispay\Migration;

use Doctrine\DBAL\Connection;
use Satispay\Handler\PaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1601909028CreateEuroRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1601909028;
    }

    public function update(Connection $connection): void
    {
        //add rule for euro
        $usaCountryId = $connection->executeQuery('SELECT LOWER(hex(id)) FROM currency WHERE `iso_code` = "EUR"')->fetchColumn();

        $euroRuleId = Uuid::randomBytes();
        $connection->insert(
            'rule',
            [
                'id' => $euroRuleId,
                'name' => 'Currency Euro',
                'priority' => 1,
                'invalid' => 0,
                'description' => 'Satispay rule to hide payment for orders with currency different from EURO',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), ]
        );

        $connection->insert(
            'rule_condition',
            ['id' => Uuid::randomBytes(),
                'rule_id' => $euroRuleId,
                'type' => 'currency',
                'value' => json_encode(
                    ['operator' => \Shopware\Core\Framework\Rule\Rule::OPERATOR_EQ,
                        'currencyIds' => [$usaCountryId],
                    ]
                ),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT), ]
        );

        //set the new rule to payment
        $connection->executeUpdate(
            'UPDATE payment_method SET availability_rule_id = :value where handler_identifier = :handler',
            ['value' => $euroRuleId, 'handler' => PaymentHandler::class]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
