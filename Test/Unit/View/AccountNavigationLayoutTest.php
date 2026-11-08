<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class AccountNavigationLayoutTest extends TestCase
{
    public function testAlertsLinkTargetsCoreNavigationBlock(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/../../../view/frontend/layout/customer_account.xml');

        // Core's block is customer_account_navigation; a misspelt reference is silently ignored and the link never shows.
        $this->assertSame('customer_account_navigation', (string) $xml->body->referenceBlock['name']);
    }
}
