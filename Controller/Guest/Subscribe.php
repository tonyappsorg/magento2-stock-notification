<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Controller\Guest;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Tonyapps\StockNotification\Helper\Data as Helper;
use Tonyapps\StockNotification\Model\GuestAlert;
use Tonyapps\StockNotification\Model\GuestAlertSender;
use Tonyapps\StockNotification\Model\GuestSubscriber;

/**
 * Ajax endpoint behind the product-page pop-up. Form key is enforced by the default CSRF validator. A new
 * subscription only gets a confirmation email; the admin hears about it once it is confirmed (Confirm controller).
 */
class Subscribe implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly GuestSubscriber $subscriber,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Helper $helper,
        private readonly GuestAlertSender $sender
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $store = $this->storeManager->getStore();

        if (!$this->helper->isGuestSubscriptionAllowed((int) $store->getId())) {
            return $result->setData(['success' => false, 'message' => __('Guest subscriptions are not available.')]);
        }

        $productId = (int) $this->request->getParam('product_id');
        $alertType = (string) $this->request->getParam('alert_type', GuestAlert::TYPE_STOCK);

        try {
            $product = $this->productRepository->getById($productId, false, (int) $store->getId());
            // Price is taken server side: the browser does not get to say what a price drop is.
            $price = $alertType === GuestAlert::TYPE_PRICE ? (float) $product->getFinalPrice() : null;

            $alert = $this->subscriber->subscribe(
                (string) $this->request->getParam('email'),
                $productId,
                $alertType,
                (int) $store->getId(),
                (int) $store->getWebsiteId(),
                $price
            );
            if ($alert->isObjectNew()) {
                $this->sender->sendConfirmation($alert, $product);
            }
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('We could not save your subscription. Please try again later.'),
            ]);
        }

        if ((int) $alert->getStatus() === GuestAlert::STATUS_UNCONFIRMED) {
            return $result->setData([
                'success' => true,
                'message' => __('Almost done: check your inbox and confirm the alert.'),
            ]);
        }

        return $result->setData([
            'success' => true,
            'message' => $alertType === GuestAlert::TYPE_PRICE
                ? __('We will email you when the price drops.')
                : __('We will email you as soon as this product is back in stock.'),
        ]);
    }
}
