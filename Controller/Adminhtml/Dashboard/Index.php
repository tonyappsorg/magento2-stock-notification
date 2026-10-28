<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Tonyapps_StockNotification::dashboard';

    public function execute(): ResultInterface
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(self::ADMIN_RESOURCE);
        $page->getConfig()->getTitle()->prepend(__('Stock Notification Analytics'));

        return $page;
    }
}
