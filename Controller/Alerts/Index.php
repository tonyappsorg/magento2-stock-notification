<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Controller\Alerts;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * "My Product Alerts" in the customer account - core ships the alerts but no page to manage them.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly Session $customerSession,
        private readonly PageFactory $pageFactory,
        private readonly ResultFactory $resultFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('customer/account/login');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('My Product Alerts'));

        return $page;
    }
}
