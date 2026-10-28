<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Controller\Guest;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Tonyapps\StockNotification\Model\GuestSubscriber;

/**
 * Unsubscribe link from the alert emails. GET only shows a button, so link prefetchers can't unsubscribe anyone;
 * POST does it. The token is the only credential a guest has, so there is no form key.
 */
class Unsubscribe implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly GuestSubscriber $subscriber,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->request->isPost()) {
            return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        }

        $removed = $this->subscriber->unsubscribeByToken((string) $this->request->getParam('token', ''));

        $removed
            ? $this->messageManager->addSuccessMessage(__('You will no longer receive alerts for this product.'))
            : $this->messageManager->addErrorMessage(__('This unsubscribe link is no longer valid.'));

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('/');
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
