<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Test\Unit\Controller\Guest;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Tonyapps\StockNotification\Controller\Guest\Subscribe;
use Tonyapps\StockNotification\Helper\Data as Helper;
use Tonyapps\StockNotification\Model\GuestAlert;
use Tonyapps\StockNotification\Model\GuestAlertSender;
use Tonyapps\StockNotification\Model\GuestSubscriber;

class SubscribeTest extends TestCase
{
    private array $response = [];

    public static function unavailableProducts(): array
    {
        return [
            'disabled' => [Status::STATUS_DISABLED, true, [1]],
            'not visible' => [Status::STATUS_ENABLED, false, [1]],
            'other website' => [Status::STATUS_ENABLED, true, [2]],
        ];
    }

    /**
     * @dataProvider unavailableProducts
     */
    public function testRefusesProductsAShopperCannotSeeHere(int $status, bool $visible, array $websiteIds): void
    {
        $subscriber = $this->createMock(GuestSubscriber::class);
        $subscriber->expects(self::never())->method('subscribe');
        $sender = $this->createMock(GuestAlertSender::class);
        $sender->expects(self::never())->method('sendConfirmation');

        $this->controller($this->product($status, $visible, $websiteIds), $subscriber, $sender)->execute();

        self::assertFalse($this->response['success']);
    }

    public function testSendsConfirmationWhenDue(): void
    {
        $alert = $this->alert(['status' => GuestAlert::STATUS_UNCONFIRMED, 'confirmation_due' => true]);
        $subscriber = $this->createMock(GuestSubscriber::class);
        $subscriber->method('subscribe')->willReturn($alert);
        $sender = $this->createMock(GuestAlertSender::class);
        $sender->expects(self::once())->method('sendConfirmation');

        $this->controller($this->product(Status::STATUS_ENABLED, true, ['1']), $subscriber, $sender)->execute();

        self::assertTrue($this->response['success']);
    }

    public function testDoesNotResendConfirmationWhenNotDue(): void
    {
        $alert = $this->alert(['status' => GuestAlert::STATUS_UNCONFIRMED, 'confirmation_due' => false]);
        $subscriber = $this->createMock(GuestSubscriber::class);
        $subscriber->method('subscribe')->willReturn($alert);
        $sender = $this->createMock(GuestAlertSender::class);
        $sender->expects(self::never())->method('sendConfirmation');

        $this->controller($this->product(Status::STATUS_ENABLED, true, [1]), $subscriber, $sender)->execute();

        self::assertTrue($this->response['success']);
    }

    private function controller(Product $product, GuestSubscriber $subscriber, GuestAlertSender $sender): Subscribe
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([
            ['product_id', null, '5'],
            ['alert_type', GuestAlert::TYPE_STOCK, GuestAlert::TYPE_STOCK],
            ['email', null, 'shopper@example.com'],
        ]);
        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(function (array $data) use (&$json) {
            $this->response = $data;
            return $json;
        });
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturn($json);
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('getById')->willReturn($product);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $helper = $this->createMock(Helper::class);
        $helper->method('isGuestSubscriptionAllowed')->willReturn(true);

        return new Subscribe($request, $resultFactory, $subscriber, $repository, $storeManager, $helper, $sender);
    }

    private function product(int $status, bool $visible, array $websiteIds): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getStatus')->willReturn($status);
        $product->method('isVisibleInSiteVisibility')->willReturn($visible);
        $product->method('getWebsiteIds')->willReturn($websiteIds);

        return $product;
    }

    private function alert(array $data): GuestAlert
    {
        $alert = $this->getMockBuilder(GuestAlert::class)->disableOriginalConstructor()->onlyMethods([])->getMock();

        return $alert->setData($data);
    }
}
