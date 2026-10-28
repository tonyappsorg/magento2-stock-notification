<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Model;

use Magento\Framework\Model\AbstractModel;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert as GuestAlertResource;

/**
 * @method string getEmail()
 * @method int getProductId()
 * @method int getStoreId()
 * @method int getWebsiteId()
 * @method string getAlertType()
 * @method float|null getPrice()
 * @method int getStatus()
 * @method string getUnsubscribeToken()
 * @method int getSendCount()
 */
class GuestAlert extends AbstractModel
{
    public const TYPE_STOCK = 'stock';
    public const TYPE_PRICE = 'price';

    public const STATUS_PENDING = 0;
    public const STATUS_NOTIFIED = 1;
    // Double opt-in: nothing is sent until the address owner follows the confirmation link.
    public const STATUS_UNCONFIRMED = 2;

    protected function _construct()
    {
        $this->_init(GuestAlertResource::class);
    }
}
