<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Read-only aggregates over the core alert tables plus the guest table, for the admin dashboard.
 */
class Analytics implements ArgumentInterface
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * @return array{customer_stock: int, customer_price: int, guest_pending: int, guest_notified: int, alerts_sent: int}
     */
    public function getTotals(): array
    {
        $connection = $this->resource->getConnection();
        $stock = $this->resource->getTableName('product_alert_stock');
        $price = $this->resource->getTableName('product_alert_price');
        $guest = $this->resource->getTableName('tonyapps_stocknotification_guest');

        $count = static fn (string $table, string $where = '') => (int) $connection->fetchOne(
            $connection->select()->from($table, 'COUNT(*)')->where($where ?: '1 = 1')
        );
        $sent = static fn (string $table) => (int) $connection->fetchOne(
            $connection->select()->from($table, 'SUM(send_count)')
        );

        return [
            'customer_stock' => $count($stock),
            'customer_price' => $count($price),
            'guest_pending' => $count($guest, 'status = 0'),
            'guest_notified' => $count($guest, 'status = 1'),
            'alerts_sent' => $sent($stock) + $sent($price) + $sent($guest),
        ];
    }

    /**
     * Products ranked by how many people are waiting on them - the refill shortlist.
     *
     * @return array<int, array{product_id: int, name: string, sku: string, subscribers: int, guests: int, alerts_sent: int}>
     */
    public function getTopProducts(int $limit = 25): array
    {
        $connection = $this->resource->getConnection();
        $union = $connection->select()->union([
            $connection->select()->from(
                $this->resource->getTableName('product_alert_stock'),
                ['product_id', 'subs' => 'COUNT(*)', 'guests' => new \Zend_Db_Expr('0'), 'sent' => 'SUM(send_count)']
            )->group('product_id'),
            $connection->select()->from(
                $this->resource->getTableName('product_alert_price'),
                ['product_id', 'subs' => 'COUNT(*)', 'guests' => new \Zend_Db_Expr('0'), 'sent' => 'SUM(send_count)']
            )->group('product_id'),
            $connection->select()->from(
                $this->resource->getTableName('tonyapps_stocknotification_guest'),
                ['product_id', 'subs' => 'COUNT(*)', 'guests' => 'COUNT(*)', 'sent' => 'SUM(send_count)']
            )->where('status <> ?', \Tonyapps\StockNotification\Model\GuestAlert::STATUS_UNCONFIRMED)->group('product_id'),
        ], \Magento\Framework\DB\Select::SQL_UNION_ALL);

        $select = $connection->select()
            ->from(['t' => new \Zend_Db_Expr('(' . $union . ')')], [
                'product_id',
                'subscribers' => 'SUM(t.subs)',
                'guests' => 'SUM(t.guests)',
                'alerts_sent' => 'SUM(t.sent)',
            ])
            ->group('t.product_id')
            ->order('subscribers DESC')
            ->limit($limit);

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $productId = (int) $row['product_id'];
            try {
                $product = $this->productRepository->getById($productId);
                $name = (string) $product->getName();
                $sku = (string) $product->getSku();
            } catch (\Exception $e) {
                $name = (string) __('Deleted product #%1', $productId);
                $sku = '';
            }
            $rows[] = [
                'product_id' => $productId,
                'name' => $name,
                'sku' => $sku,
                'subscribers' => (int) $row['subscribers'],
                'guests' => (int) $row['guests'],
                'alerts_sent' => (int) $row['alerts_sent'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{email: string, product_id: int, alert_type: string, add_date: string}>
     */
    public function getRecentGuestSubscriptions(int $limit = 20): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('tonyapps_stocknotification_guest'),
                ['email', 'product_id', 'alert_type', 'add_date', 'status']
            )
            ->order('add_date DESC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }
}
