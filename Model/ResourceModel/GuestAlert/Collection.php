<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Model\ResourceModel\GuestAlert;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tonyapps\StockNotification\Model\GuestAlert;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert as GuestAlertResource;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(GuestAlert::class, GuestAlertResource::class);
    }
}
