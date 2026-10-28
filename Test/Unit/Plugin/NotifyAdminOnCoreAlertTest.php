<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Test\Unit\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\ProductAlert\Model\Stock;
use PHPUnit\Framework\TestCase;
use Tonyapps\StockNotification\Model\AdminNotifier;
use Tonyapps\StockNotification\Model\GuestAlert;
use Tonyapps\StockNotification\Plugin\NotifyAdminOnCoreAlert;

class NotifyAdminOnCoreAlertTest extends TestCase
{
    public function testNewStockAlertNotifiesAdminAfterSave(): void
    {
        $subject = $this->alert(['customer_id' => 6, 'product_id' => 9, 'send_count' => null]);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('buyer@example.test');
        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->expects(self::once())->method('getById')->with(6)->willReturn($customer);
        $product = $this->createMock(ProductInterface::class);
        $product->method('getName')->willReturn('Product');
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())->method('getById')->with(9)->willReturn($product);
        $notifier = $this->createMock(AdminNotifier::class);
        $notifier->expects(self::once())->method('notifyNewSubscription')
            ->with('buyer@example.test', 'Product', GuestAlert::TYPE_STOCK, false);

        $result = (new NotifyAdminOnCoreAlert($notifier, $customerRepository, $productRepository))
            ->aroundSave($subject, static fn() => 'saved');

        self::assertSame('saved', $result);
    }

    public function testExistingAlertDoesNotSendDuplicateAdminNotice(): void
    {
        $subject = $this->alert(['customer_id' => 6, 'product_id' => 9, 'send_count' => 1], 10);
        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->expects(self::never())->method('getById');
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('getById');
        $notifier = $this->createMock(AdminNotifier::class);
        $notifier->expects(self::never())->method('notifyNewSubscription');

        self::assertSame('saved', (new NotifyAdminOnCoreAlert($notifier, $customerRepository, $productRepository))
            ->aroundSave($subject, static fn() => 'saved'));
    }

    /** @param array<string, mixed> $data */
    private function alert(array $data, ?int $id = null): Stock
    {
        $subject = $this->getMockBuilder(Stock::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getData'])
            ->getMock();
        $subject->method('getId')->willReturn($id);
        $subject->method('getData')->willReturnCallback(static fn(?string $key = null) => $key === null ? $data : ($data[$key] ?? null));
        return $subject;
    }
}
