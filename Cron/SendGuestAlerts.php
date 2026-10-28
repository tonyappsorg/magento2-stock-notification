<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Cron;

use Tonyapps\StockNotification\Model\GuestAlertSender;

class SendGuestAlerts
{
    public function __construct(private readonly GuestAlertSender $sender)
    {
    }

    public function execute(): void
    {
        $this->sender->send();
    }
}
