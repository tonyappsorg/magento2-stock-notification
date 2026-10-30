<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Block\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\ProductAlert\Helper\Data as ProductAlertHelper;
use Tonyapps\StockNotification\Helper\Data as Helper;

/**
 * Replaces the core product-page alert links so guests get an email field instead of a login redirect.
 */
class Subscribe extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly ProductAlertHelper $productAlertHelper,
        private readonly Helper $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof Product && $product->getId() ? $product : null;
    }

    public function isStockAlertVisible(): bool
    {
        $product = $this->getProduct();

        return $product !== null && !$product->isAvailable() && $this->productAlertHelper->isStockAlertAllowed();
    }

    public function isPriceAlertVisible(): bool
    {
        $product = $this->getProduct();

        return $product !== null && $product->isAvailable() && $this->productAlertHelper->isPriceAlertAllowed();
    }

    /**
     * Whether to render the guest email form next to the core links. The product page is full-page cached, so the
     * session can't tell guests from customers here; the template picks one client side from customer-data.
     * Guests fall back to the core login-gated link when guest subscriptions are switched off.
     */
    public function isGuestFormAllowed(): bool
    {
        return $this->helper->isGuestSubscriptionAllowed((int) $this->_storeManager->getStore()->getId());
    }

    public function isPopup(): bool
    {
        return $this->helper->isPopupEnabled((int) $this->_storeManager->getStore()->getId());
    }

    /**
     * Core signup url, used for logged-in customers so their alerts land in product_alert_* as usual.
     */
    public function getCoreSignupUrl(string $type): string
    {
        return $this->productAlertHelper->getSaveUrl($type);
    }

}
