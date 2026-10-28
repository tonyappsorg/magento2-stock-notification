<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Controller\Guest;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Tonyapps\StockNotification\Model\AdminNotifier;
use Tonyapps\StockNotification\Model\GuestSubscriber;

/**
 * Confirmation link from the double opt-in email. GET only shows a button, so mail scanners that follow links
 * can't confirm on the owner's behalf; POST confirms. The token is the credential, so there is no form key.
 */
class Confirm implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly GuestSubscriber $subscriber,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AdminNotifier $notifier,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->request->isPost()) {
            return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        }

        $alert = $this->subscriber->confirmByToken((string) $this->request->getParam('token', ''));
        if (!$alert) {
            $this->messageManager->addErrorMessage(__('This link is no longer valid, or the alert is already confirmed.'));
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('/');
        }

        $this->messageManager->addSuccessMessage(__('Confirmed. We will email you when it happens.'));
        try {
            $product = $this->productRepository->getById((int) $alert->getProductId(), false, (int) $alert->getStoreId());
            $this->notifier->notifyNewSubscription($alert->getEmail(), (string) $product->getName(), $alert->getAlertType(), true);
        } catch (\Exception $e) {
            // Product deleted in the meantime; the notifier swallows mail errors itself.
        }

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
