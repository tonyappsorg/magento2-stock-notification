<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Test\Unit\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\ProductAlert\Helper\Data as ProductAlertHelper;
use Magento\ProductAlert\Model\ProductSalability;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\StockNotification\Helper\Data as Helper;
use Tonyapps\StockNotification\Model\GuestAlert;
use Tonyapps\StockNotification\Model\GuestAlertSender;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert as GuestAlertResource;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert\Collection;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert\CollectionFactory;

class GuestAlertSenderTest extends TestCase
{
    public function testStockEmailShowsThePriceInTheStoreCurrency(): void
    {
        $alert = $this->getMockBuilder(GuestAlert::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $alert->setData([
            'email' => 'shopper@example.com',
            'product_id' => 9,
            'store_id' => 2,
            'alert_type' => GuestAlert::TYPE_STOCK,
            'unsubscribe_token' => 'tok',
        ]);
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$alert]));
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $product = $this->createMock(Product::class);
        $product->method('getFinalPrice')->willReturn(10.0);
        $product->method('getName')->willReturn('Mug');
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('getById')->willReturn($product);
        $salability = $this->createMock(ProductSalability::class);
        $salability->method('isSalable')->willReturn(true);
        $alertHelper = $this->createMock(ProductAlertHelper::class);
        $alertHelper->method('isStockAlertAllowed')->willReturn(true);
        $helper = $this->createMock(Helper::class);
        $helper->method('isGuestSubscriptionAllowed')->willReturn(true);
        $store = $this->createMock(Store::class);
        $store->method('getWebsite')->willReturn($this->createMock(Website::class));
        $store->method('getUrl')->willReturn('https://example.test/unsubscribe');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->expects(self::once())->method('convertAndFormat')->with(10.0, false, 2, 2)->willReturn('€9.00');
        $priceCurrency->expects(self::never())->method('format');

        $vars = [];
        $transportBuilder = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setFromByScope', 'addTo'] as $method) {
            $transportBuilder->method($method)->willReturnSelf();
        }
        $transportBuilder->method('setTemplateVars')->willReturnCallback(
            function (array $templateVars) use (&$vars, $transportBuilder) {
                $vars = $templateVars;
                return $transportBuilder;
            }
        );
        $transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $escaper->method('escapeUrl')->willReturnArgument(0);

        (new GuestAlertSender(
            $collectionFactory,
            $this->createMock(GuestAlertResource::class),
            $repository,
            $salability,
            $alertHelper,
            $helper,
            $storeManager,
            $this->createMock(Emulation::class),
            $transportBuilder,
            $priceCurrency,
            $escaper,
            $this->createMock(LoggerInterface::class)
        ))->send();

        self::assertStringContainsString('€9.00', $vars['productsHtml']);
    }
}
