<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Controller\Alerts;

use Magento\Customer\Controller\AccountInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * "My Product Alerts" in the customer account - core ships the alerts but no page to manage them.
 */
class Index implements HttpGetActionInterface, AccountInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('My Product Alerts'));

        return $page;
    }
}
