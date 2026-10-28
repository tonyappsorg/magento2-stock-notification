<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Model;

use Magento\Framework\App\Area;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Tonyapps\StockNotification\Helper\Data as Helper;

/**
 * Admin-facing emails: new subscriptions and the daily low stock digest.
 */
class AdminNotifier
{
    private const SENDER = 'general';

    public function __construct(
        private readonly Helper $helper,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    public function notifyNewSubscription(string $subscriberEmail, string $productName, string $alertType, bool $isGuest): void
    {
        if (!$this->helper->isNewSubscriptionNotificationEnabled()) {
            return;
        }
        $this->send('tonyapps_stocknotification_admin_subscription', [
            'subscriber_email' => $subscriberEmail,
            'product_name' => $productName,
            'alert_type' => $alertType,
            // Cast: a Phrase object renders empty in email templates.
            'subscriber_type' => (string) ($isGuest ? __('Guest') : __('Customer')),
        ]);
    }

    /**
     * @param array<int, array{sku: string, name: string, qty: float, source: string}> $rows
     */
    public function notifyLowStock(array $rows): void
    {
        if (!$rows || !$this->helper->isLowStockNotificationEnabled()) {
            return;
        }

        // Email templates cannot loop reliably across versions, so the table is rendered here.
        $rowsHtml = '';
        foreach ($rows as $row) {
            $rowsHtml .= sprintf(
                '<tr><td>%s</td><td>%s</td><td align="right">%s</td><td>%s</td></tr>',
                $this->escaper->escapeHtml($row['sku']),
                $this->escaper->escapeHtml($row['name']),
                $this->escaper->escapeHtml((string) $row['qty']),
                $this->escaper->escapeHtml($row['source'])
            );
        }

        $this->send('tonyapps_stocknotification_admin_low_stock', [
            'rowsHtml' => $rowsHtml,
            'rowCount' => count($rows),
        ]);
    }

    private function send(string $templateId, array $vars): void
    {
        $recipient = $this->helper->getAdminRecipient();
        if (!$recipient) {
            return;
        }

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    // Frontend: the email header/footer templates only exist there; adminhtml renders an error page instead.
                    'area' => Area::AREA_FRONTEND,
                    'store' => $this->storeManager->getDefaultStoreView()->getId(),
                ])
                ->setTemplateVars($vars)
                ->setFromByScope(self::SENDER)
                ->addTo($recipient)
                ->getTransport();
            $transport->sendMessage();
        } catch (\Exception $e) {
            // An admin notification is never worth failing the customer's request or the cron run over.
            $this->logger->error('Stock notification admin email failed: ' . $e->getMessage());
        }
    }
}
