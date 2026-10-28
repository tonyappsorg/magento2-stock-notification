<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\ProductAlert\Model\Stock;
use Tonyapps\StockNotification\Model\AdminNotifier;
use Tonyapps\StockNotification\Model\GuestAlert;

/**
 * Core has no event on alert creation, so hook the model save. Applies to both Stock and Price alerts.
 */
class NotifyAdminOnCoreAlert
{
    public function __construct(
        private readonly AdminNotifier $notifier,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function aroundSave(AbstractModel $subject, callable $proceed)
    {
        $isNew = !$subject->getId();
        $result = $proceed();

        // Core's _beforeSave merges an existing row for the same customer/product into the model on re-subscribe;
        // only that row carries send_count, a fresh alert leaves it to the column default.
        if ($isNew && $subject->getData('send_count') === null) {
            try {
                $customer = $this->customerRepository->getById((int) $subject->getData('customer_id'));
                $product = $this->productRepository->getById((int) $subject->getData('product_id'));
                $this->notifier->notifyNewSubscription(
                    (string) $customer->getEmail(),
                    (string) $product->getName(),
                    $subject instanceof Stock ? GuestAlert::TYPE_STOCK : GuestAlert::TYPE_PRICE,
                    false
                );
            } catch (\Exception $e) {
                // Notifier already swallows mail errors; this covers a deleted customer or product.
            }
        }

        return $result;
    }
}
