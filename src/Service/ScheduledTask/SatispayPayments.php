<?php declare(strict_types=1);

namespace Satispay\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SatispayPayments extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'satispay.payment_scheduled_task';
    }

    public static function getDefaultInterval(): int
    {
        return 14400; // 4 hours
    }
}
