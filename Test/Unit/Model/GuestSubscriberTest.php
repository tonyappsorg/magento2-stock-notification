<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use PHPUnit\Framework\TestCase;
use Tonyapps\StockNotification\Model\GuestAlert;
use Tonyapps\StockNotification\Model\GuestAlertFactory;
use Tonyapps\StockNotification\Model\GuestSubscriber;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert as GuestAlertResource;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert\Collection;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert\CollectionFactory;

class GuestSubscriberTest extends TestCase
{
    private GuestAlertResource $resource;
    private GuestSubscriber $subscriber;
    private ?GuestAlert $existing = null;
    private int $unconfirmedCount = 0;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(GuestAlertResource::class);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturnCallback(fn () => $this->existing ?? $this->newAlert());
        $collection->method('getSize')->willReturnCallback(fn () => $this->unconfirmedCount);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $alertFactory = $this->createMock(GuestAlertFactory::class);
        $alertFactory->method('create')->willReturnCallback(fn () => $this->newAlert());

        $random = $this->createMock(Random::class);
        $random->method('getUniqueHash')->willReturn('token-abc');

        $this->subscriber = new GuestSubscriber($alertFactory, $this->resource, $collectionFactory, $random);
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(LocalizedException::class);
        $this->subscriber->subscribe('not-an-email', 1, GuestAlert::TYPE_STOCK, 1, 1);
    }

    public function testRejectsUnknownAlertType(): void
    {
        $this->expectException(LocalizedException::class);
        $this->subscriber->subscribe('shopper@example.com', 1, 'weather', 1, 1);
    }

    public function testRejectsMissingProduct(): void
    {
        $this->expectException(LocalizedException::class);
        $this->subscriber->subscribe('shopper@example.com', 0, GuestAlert::TYPE_STOCK, 1, 1);
    }

    public function testNewSubscriptionWaitsForConfirmation(): void
    {
        $this->resource->expects($this->once())->method('save');

        $alert = $this->subscriber->subscribe('  shopper@example.com ', 42, GuestAlert::TYPE_PRICE, 3, 1, 19.99);

        $this->assertSame('shopper@example.com', $alert->getEmail());
        $this->assertSame(42, $alert->getProductId());
        $this->assertSame(GuestAlert::TYPE_PRICE, $alert->getAlertType());
        $this->assertSame(19.99, $alert->getPrice());
        $this->assertSame(GuestAlert::STATUS_UNCONFIRMED, $alert->getStatus());
        $this->assertSame('token-abc', $alert->getUnsubscribeToken());
        $this->assertTrue($alert->isObjectNew());
        $this->assertTrue($alert->getData('confirmation_due'), 'caller sends the confirmation email for new alerts');
    }

    public function testRefusesNewSubscriptionOnceTheAddressHasTooManyUnconfirmed(): void
    {
        $this->unconfirmedCount = 5;
        $this->resource->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->subscriber->subscribe('victim@example.com', 42, GuestAlert::TYPE_STOCK, 1, 1);
    }

    public function testResubscribingAfterANotificationReArmsWithoutConfirmation(): void
    {
        $this->existing = $this->newAlert()->setData(['entity_id' => 7, 'status' => GuestAlert::STATUS_NOTIFIED, 'unsubscribe_token' => 'old']);

        $alert = $this->subscriber->subscribe('shopper@example.com', 42, GuestAlert::TYPE_STOCK, 1, 1);

        $this->assertSame(GuestAlert::STATUS_PENDING, $alert->getStatus());
        $this->assertFalse($alert->isObjectNew());
        $this->assertSame('old', $alert->getUnsubscribeToken());
    }

    public function testResubscribingWhileUnconfirmedWithinAnHourSendsNothing(): void
    {
        $this->existing = $this->newAlert()->setData([
            'entity_id' => 7,
            'status' => GuestAlert::STATUS_UNCONFIRMED,
            'unsubscribe_token' => 'old',
            'add_date' => gmdate('Y-m-d H:i:s', time() - 600),
        ]);

        $alert = $this->subscriber->subscribe('shopper@example.com', 42, GuestAlert::TYPE_STOCK, 1, 1);

        $this->assertSame(GuestAlert::STATUS_UNCONFIRMED, $alert->getStatus());
        $this->assertFalse($alert->isObjectNew());
        $this->assertFalse($alert->getData('confirmation_due'));
    }

    public function testResubscribingWhileUnconfirmedAfterAnHourResendsTheConfirmation(): void
    {
        $this->existing = $this->newAlert()->setData([
            'entity_id' => 7,
            'status' => GuestAlert::STATUS_UNCONFIRMED,
            'unsubscribe_token' => 'old',
            'add_date' => gmdate('Y-m-d H:i:s', time() - 7200),
        ]);

        $alert = $this->subscriber->subscribe('shopper@example.com', 42, GuestAlert::TYPE_STOCK, 1, 1);

        $this->assertSame(GuestAlert::STATUS_UNCONFIRMED, $alert->getStatus());
        $this->assertTrue($alert->getData('confirmation_due'));
        $this->assertSame('old', $alert->getUnsubscribeToken(), 'the resent link is the one already emailed');
        $this->assertGreaterThan(time() - 60, strtotime($alert->getData('add_date') . ' UTC'), 'throttle restarts');
    }

    public function testConfirmArmsAnUnconfirmedAlertOnce(): void
    {
        $this->existing = $this->newAlert()->setData(['entity_id' => 7, 'status' => GuestAlert::STATUS_UNCONFIRMED]);

        $this->assertSame($this->existing, $this->subscriber->confirmByToken('tok'));
        $this->assertSame(GuestAlert::STATUS_PENDING, $this->existing->getStatus());
        $this->assertNull($this->subscriber->confirmByToken('tok'), 'second click must not notify the admin again');
    }

    public function testEmptyTokenIsNotAnUnsubscribe(): void
    {
        $this->resource->expects($this->never())->method('delete');

        $this->assertFalse($this->subscriber->unsubscribeByToken(''));
    }

    private function newAlert(): GuestAlert
    {
        $alert = $this->getMockBuilder(GuestAlert::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        // The skipped constructor would set this; getId() needs it to tell an existing alert from a new one.
        return $alert->setIdFieldName('entity_id');
    }
}
