<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Tonyapps\StockNotification\Model\Analytics;

class Dashboard extends Template
{
    public function __construct(
        Context $context,
        private readonly Analytics $analytics,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getTotals(): array
    {
        return $this->analytics->getTotals();
    }

    public function getTopProducts(): array
    {
        return $this->analytics->getTopProducts();
    }

    public function getRecentGuestSubscriptions(): array
    {
        return $this->analytics->getRecentGuestSubscriptions();
    }

    public function getProductEditUrl(int $productId): string
    {
        return $this->getUrl('catalog/product/edit', ['id' => $productId]);
    }
}
