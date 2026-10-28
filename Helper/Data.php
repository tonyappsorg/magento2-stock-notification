<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'tonyapps_stocknotification/general/enabled';
    private const XML_PATH_GUEST_ENABLED = 'tonyapps_stocknotification/general/guest_enabled';
    private const XML_PATH_POPUP_ENABLED = 'tonyapps_stocknotification/general/popup_enabled';
    private const XML_PATH_RECIPIENT = 'tonyapps_stocknotification/admin/recipient_email';
    private const XML_PATH_NOTIFY_SUBSCRIPTION = 'tonyapps_stocknotification/admin/notify_new_subscription';
    private const XML_PATH_NOTIFY_LOW_STOCK = 'tonyapps_stocknotification/admin/notify_low_stock';
    private const XML_PATH_GENERAL_CONTACT = 'trans_email/ident_general/email';

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_ENABLED, $storeId);
    }

    public function isGuestSubscriptionAllowed(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->flag(self::XML_PATH_GUEST_ENABLED, $storeId);
    }

    public function isPopupEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_POPUP_ENABLED, $storeId);
    }

    public function isNewSubscriptionNotificationEnabled(): bool
    {
        return $this->isEnabled() && $this->flag(self::XML_PATH_NOTIFY_SUBSCRIPTION);
    }

    public function isLowStockNotificationEnabled(): bool
    {
        return $this->isEnabled() && $this->flag(self::XML_PATH_NOTIFY_LOW_STOCK);
    }

    public function getAdminRecipient(): ?string
    {
        $email = (string) $this->scopeConfig->getValue(self::XML_PATH_RECIPIENT, ScopeInterface::SCOPE_STORE);
        if ($email === '') {
            $email = (string) $this->scopeConfig->getValue(self::XML_PATH_GENERAL_CONTACT, ScopeInterface::SCOPE_STORE);
        }
        return $email !== '' ? $email : null;
    }

    private function flag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
