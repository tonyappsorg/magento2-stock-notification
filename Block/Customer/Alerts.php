<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Block\Customer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Helper\PostHelper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\ProductAlert\Model\ResourceModel\Price\CollectionFactory as PriceCollectionFactory;
use Magento\ProductAlert\Model\ResourceModel\Stock\CollectionFactory as StockCollectionFactory;
use Tonyapps\StockNotification\Model\GuestAlert;

/**
 * Lists the signed-in customer's core product alerts. Unsubscribing reuses core's own controllers.
 */
class Alerts extends Template
{
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly StockCollectionFactory $stockCollectionFactory,
        private readonly PriceCollectionFactory $priceCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly PostHelper $postHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array{type: string, label: \Magento\Framework\Phrase, name: string, url: string, add_date: string, unsubscribe: string}>
     */
    public function getAlerts(): array
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $websiteId = (int) $this->_storeManager->getStore()->getWebsiteId();
        if (!$customerId) {
            return [];
        }

        $rows = [];
        foreach ([GuestAlert::TYPE_STOCK, GuestAlert::TYPE_PRICE] as $type) {
            $factory = $type === GuestAlert::TYPE_STOCK ? $this->stockCollectionFactory : $this->priceCollectionFactory;
            $collection = $factory->create()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('website_id', $websiteId);

            foreach ($collection as $alert) {
                try {
                    $product = $this->productRepository->getById((int) $alert->getProductId());
                } catch (\Exception $e) {
                    continue;
                }
                $rows[] = [
                    'type' => $type,
                    'label' => $type === GuestAlert::TYPE_STOCK ? __('Back in stock') : __('Price drop'),
                    'name' => (string) $product->getName(),
                    'url' => (string) $product->getProductUrl(),
                    'add_date' => (string) $alert->getAddDate(),
                    'unsubscribe' => $this->postHelper->getPostData(
                        $this->getUrl('productalert/unsubscribe/' . $type),
                        ['product' => (int) $product->getId()]
                    ),
                ];
            }
        }

        return $rows;
    }
}
