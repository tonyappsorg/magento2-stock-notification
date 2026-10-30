<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime;
use Magento\ProductAlert\Helper\Data as ProductAlertHelper;
use Magento\ProductAlert\Model\ProductSalability;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Tonyapps\StockNotification\Helper\Data as Helper;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert as GuestAlertResource;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert\CollectionFactory;

/**
 * Guest counterpart to Magento\ProductAlert\Model\Observer::process, which only handles logged-in customers.
 */
class GuestAlertSender
{
    private const TEMPLATES = [
        GuestAlert::TYPE_STOCK => 'tonyapps_stocknotification_guest_stock',
        GuestAlert::TYPE_PRICE => 'tonyapps_stocknotification_guest_price',
    ];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly GuestAlertResource $alertResource,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductSalability $productSalability,
        private readonly ProductAlertHelper $productAlertHelper,
        private readonly Helper $helper,
        private readonly StoreManagerInterface $storeManager,
        private readonly Emulation $emulation,
        private readonly TransportBuilder $transportBuilder,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    public function send(): void
    {
        $this->alertResource->purge();

        // ponytail: loads all pending alerts at once. Add paging if a store ever parks 100k+ of them.
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('status', GuestAlert::STATUS_PENDING)
            ->setOrder('store_id', 'ASC')
            ->setOrder('email', 'ASC');

        $buckets = [];
        /** @var GuestAlert $alert */
        foreach ($collection as $alert) {
            $buckets[(int) $alert->getStoreId()][$alert->getEmail()][$alert->getAlertType()][] = $alert;
        }

        foreach ($buckets as $storeId => $byEmail) {
            $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
            try {
                $this->processStore($storeId, $byEmail);
            } catch (\Exception $e) {
                $this->logger->error('Guest stock alerts failed for store ' . $storeId . ': ' . $e->getMessage());
            } finally {
                $this->emulation->stopEnvironmentEmulation();
            }
        }
    }

    /**
     * @param array<string, array<string, GuestAlert[]>> $byEmail
     */
    private function processStore(int $storeId, array $byEmail): void
    {
        if (!$this->helper->isGuestSubscriptionAllowed($storeId)) {
            return;
        }
        $website = $this->storeManager->getStore($storeId)->getWebsite();
        // Many shoppers wait on the same few products; load each one once per run.
        $products = [];

        foreach ($byEmail as $email => $byType) {
            foreach ($byType as $alertType => $alerts) {
                $allowed = $alertType === GuestAlert::TYPE_STOCK
                    ? $this->productAlertHelper->isStockAlertAllowed()
                    : $this->productAlertHelper->isPriceAlertAllowed();
                if (!$allowed) {
                    continue;
                }

                $triggered = [];
                foreach ($alerts as $alert) {
                    $productId = (int) $alert->getProductId();
                    if (!array_key_exists($productId, $products)) {
                        try {
                            $products[$productId] = $this->productRepository->getById($productId, false, $storeId, true);
                        } catch (\Exception $e) {
                            $products[$productId] = null;
                        }
                    }
                    $product = $products[$productId];
                    if (!$product) {
                        continue;
                    }
                    if ($alertType === GuestAlert::TYPE_STOCK) {
                        if ($this->productSalability->isSalable($product, $website)) {
                            $triggered[] = [$alert, $product];
                        }
                    } elseif ((float) $product->getFinalPrice() < (float) $alert->getPrice()) {
                        $alert->setData('price', (float) $product->getFinalPrice());
                        $triggered[] = [$alert, $product];
                    }
                }

                if ($triggered) {
                    $this->dispatch((string) $email, $alertType, $triggered, $storeId);
                }
            }
        }
    }

    /**
     * @param array<int, array{0: GuestAlert, 1: ProductInterface}> $triggered
     */
    private function dispatch(string $email, string $alertType, array $triggered, int $storeId): void
    {
        $rows = '';
        foreach ($triggered as [$alert, $product]) {
            $rows .= $this->renderRow($alert, $product, $storeId);
        }

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATES[$alertType])
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars(['productsHtml' => $rows])
                ->setFromByScope('general', $storeId)
                ->addTo($email)
                ->getTransport();
            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error('Guest alert email to ' . $email . ' failed: ' . $e->getMessage());
            return;
        }

        foreach ($triggered as [$alert, $product]) {
            $alert->addData([
                'status' => GuestAlert::STATUS_NOTIFIED,
                'send_date' => date(DateTime::DATETIME_PHP_FORMAT),
                'send_count' => (int) $alert->getSendCount() + 1,
            ]);
            $this->alertResource->save($alert);
        }
    }

    /**
     * Double opt-in: the alert stays unconfirmed until the address owner presses the button behind this link.
     *
     * @throws \Exception when the email can't be sent, so the shopper hears about it
     */
    public function sendConfirmation(GuestAlert $alert, ProductInterface $product): void
    {
        $storeId = (int) $alert->getStoreId();
        $transport = $this->transportBuilder
            ->setTemplateIdentifier('tonyapps_stocknotification_guest_confirm')
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars([
                'product_name' => (string) $product->getName(),
                'alert_type' => $alert->getAlertType(),
                'confirm_url' => $this->storeManager->getStore($storeId)->getUrl(
                    'stocknotification/guest/confirm',
                    ['token' => $alert->getUnsubscribeToken(), '_nosid' => true]
                ),
            ])
            ->setFromByScope('general', $storeId)
            ->addTo($alert->getEmail())
            ->getTransport();
        $transport->sendMessage();
    }

    private function renderRow(GuestAlert $alert, ProductInterface $product, int $storeId): string
    {
        $unsubscribeUrl = $this->storeManager->getStore($storeId)->getUrl(
            'stocknotification/guest/unsubscribe',
            ['token' => $alert->getUnsubscribeToken(), '_nosid' => true]
        );

        return sprintf(
            '<tr><td style="padding:8px 0"><a href="%s">%s</a><br/><small>%s</small><br/>'
            . '<small><a href="%s">%s</a></small></td></tr>',
            $this->escaper->escapeUrl($product->getProductUrl()),
            $this->escaper->escapeHtml($product->getName()),
            $this->escaper->escapeHtml(
                // Final price is in base currency; the shopper reads the store's display currency.
                $this->priceCurrency->convertAndFormat((float) $product->getFinalPrice(), false, 2, $storeId)
            ),
            $this->escaper->escapeUrl($unsubscribeUrl),
            $this->escaper->escapeHtml(__('Stop alerts for this product'))
        );
    }
}
