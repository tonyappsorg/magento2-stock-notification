<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Test\Unit\Block\Product;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Tonyapps\StockNotification\Block\Product\Subscribe;
use Tonyapps\StockNotification\Helper\Data as Helper;

class SubscribeTest extends TestCase
{
    /**
     * The product page is full-page cached, so the block must not decide guest vs customer from the session:
     * it only says whether the guest form may be rendered, and the browser picks one from customer-data.
     */
    public function testGuestFormFollowsStoreConfigOnly(): void
    {
        $constructor = new \ReflectionMethod(Subscribe::class, '__construct');
        $types = array_map(static fn ($p) => (string) $p->getType(), $constructor->getParameters());
        self::assertNotContains(\Magento\Customer\Model\Session::class, $types);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(3);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $helper = $this->createMock(Helper::class);
        $helper->expects(self::exactly(2))->method('isGuestSubscriptionAllowed')->with(3)
            ->willReturnOnConsecutiveCalls(true, false);

        $block = (new ObjectManager($this))->getObject(Subscribe::class, [
            'storeManager' => $storeManager,
            'helper' => $helper,
        ]);

        self::assertTrue($block->isGuestFormAllowed());
        self::assertFalse($block->isGuestFormAllowed());
    }
}
