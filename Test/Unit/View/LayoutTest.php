<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Test\Unit\View;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use PHPUnit\Framework\TestCase;
use Tonyapps\StockNotification\Model\Analytics;

class LayoutTest extends TestCase
{
    /**
     * Full page cache would otherwise store the page depersonalized, so "My Product Alerts" always renders empty.
     */
    public function testCustomerAlertsPageIsNotCached(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/../../../view/frontend/layout/stocknotification_alerts_index.xml');
        $block = $xml->xpath('//block[@name="tonyapps.stocknotification.alerts"]')[0];

        self::assertSame('false', (string) $block['cacheable']);
    }

    /**
     * Layout object arguments must implement ArgumentInterface or the admin dashboard block fails to build.
     */
    public function testDashboardViewModelIsALayoutArgument(): void
    {
        self::assertTrue(is_subclass_of(Analytics::class, ArgumentInterface::class));
    }
}
