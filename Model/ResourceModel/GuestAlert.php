<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class GuestAlert extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('tonyapps_stocknotification_guest', 'entity_id');
    }

    /**
     * Drops confirmations nobody clicked within a week and alerts sent over 180 days ago, so the table only holds
     * what someone is still waiting on. A shopper who wants a stale alert again just subscribes again.
     */
    public function purge(): void
    {
        $this->getConnection()->delete($this->getMainTable(), [
            'status = ?' => \Tonyapps\StockNotification\Model\GuestAlert::STATUS_UNCONFIRMED,
            'add_date < ?' => gmdate('Y-m-d H:i:s', strtotime('-7 days')),
        ]);
        $this->getConnection()->delete($this->getMainTable(), [
            'status = ?' => \Tonyapps\StockNotification\Model\GuestAlert::STATUS_NOTIFIED,
            'send_date < ?' => gmdate('Y-m-d H:i:s', strtotime('-180 days')),
        ]);
    }
}
