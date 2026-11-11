<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Tonyapps\StockNotification\Helper\Data;

class DataTest extends TestCase
{
    public function testEmailTemplateIsReadFromTheStoreViewsSetting(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())->method('getValue')
            ->with('tonyapps_stocknotification/email/guest_price_template', ScopeInterface::SCOPE_STORE, 3)
            ->willReturn('17');
        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($scopeConfig);

        self::assertSame('17', (new Data($context))->getEmailTemplate('guest_price', 3));
    }
}
