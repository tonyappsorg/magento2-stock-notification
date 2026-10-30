<?php
declare(strict_types=1);

namespace Tonyapps\StockNotification\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert as GuestAlertResource;
use Tonyapps\StockNotification\Model\ResourceModel\GuestAlert\CollectionFactory;

/**
 * Creates and cancels guest alert subscriptions. Kept free of request/session state so it stays testable.
 */
class GuestSubscriber
{
    // Caps confirmation emails a stranger can trigger to one address by typing it into product pages.
    private const MAX_UNCONFIRMED_PER_EMAIL = 5;
    // Re-subscribing an unconfirmed alert resends its confirmation email at most this often.
    private const CONFIRMATION_RESEND_SECONDS = 3600;

    public function __construct(
        private readonly GuestAlertFactory $alertFactory,
        private readonly GuestAlertResource $alertResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly Random $random
    ) {
    }

    /**
     * Subscribe a guest, or re-arm an existing subscription for the same email/product/store/type.
     *
     * A new subscription starts unconfirmed. The result's 'confirmation_due' flag tells the caller to email the
     * confirmation link: always for a new alert, and again for a still-unconfirmed one at most once an hour (the lost
     * email case). An address that already confirmed this alert once is re-armed straight away.
     *
     * @throws LocalizedException
     */
    public function subscribe(
        string $email,
        int $productId,
        string $alertType,
        int $storeId,
        int $websiteId,
        ?float $price = null
    ): GuestAlert {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('Please enter a valid email address.'));
        }
        if (!in_array($alertType, [GuestAlert::TYPE_STOCK, GuestAlert::TYPE_PRICE], true)) {
            throw new LocalizedException(__('Unknown alert type.'));
        }
        if ($productId <= 0) {
            throw new LocalizedException(__('Please choose a product.'));
        }

        $alert = $this->findExisting($email, $productId, $alertType, $storeId);
        $isNew = $alert === null;
        $isUnconfirmed = $isNew || (int) $alert->getStatus() === GuestAlert::STATUS_UNCONFIRMED;
        $confirmationDue = $isNew;
        if (!$isNew && $isUnconfirmed
            && strtotime((string) $alert->getData('add_date') . ' UTC') < time() - self::CONFIRMATION_RESEND_SECONDS
        ) {
            // add_date doubles as "confirmation last sent", which also restarts the 7-day purge clock.
            $alert->setData('add_date', gmdate('Y-m-d H:i:s'));
            $confirmationDue = true;
        }
        if ($isNew) {
            if ($this->countUnconfirmed($email) >= self::MAX_UNCONFIRMED_PER_EMAIL) {
                throw new LocalizedException(__('Please confirm the alerts we already emailed you first.'));
            }
            $alert = $this->alertFactory->create();
            $alert->setData('unsubscribe_token', $this->random->getUniqueHash());
        }
        $alert->addData([
            'email' => $email,
            'product_id' => $productId,
            'store_id' => $storeId,
            'website_id' => $websiteId,
            'alert_type' => $alertType,
            'price' => $price,
            'status' => $isUnconfirmed ? GuestAlert::STATUS_UNCONFIRMED : GuestAlert::STATUS_PENDING,
        ]);
        $this->alertResource->save($alert);
        $alert->isObjectNew($isNew);
        $alert->setData('confirmation_due', $confirmationDue);

        return $alert;
    }

    /**
     * Arm an unconfirmed subscription. Returns the alert when this call confirmed it, null for an unknown token or
     * one already confirmed, so the caller notifies the admin once.
     */
    public function confirmByToken(string $token): ?GuestAlert
    {
        $alert = $this->findByToken($token);
        if (!$alert || (int) $alert->getStatus() !== GuestAlert::STATUS_UNCONFIRMED) {
            return null;
        }
        $alert->setData('status', GuestAlert::STATUS_PENDING);
        $this->alertResource->save($alert);

        return $alert;
    }

    /**
     * Remove a subscription by its unsubscribe token. Returns false when the token is unknown or already used.
     */
    public function unsubscribeByToken(string $token): bool
    {
        $alert = $this->findByToken($token);
        if (!$alert) {
            return false;
        }
        $this->alertResource->delete($alert);

        return true;
    }

    private function findByToken(string $token): ?GuestAlert
    {
        if ($token === '') {
            return null;
        }
        $alert = $this->collectionFactory->create()
            ->addFieldToFilter('unsubscribe_token', $token)
            ->setPageSize(1)
            ->getFirstItem();

        return $alert->getId() ? $alert : null;
    }

    private function countUnconfirmed(string $email): int
    {
        return $this->collectionFactory->create()
            ->addFieldToFilter('email', $email)
            ->addFieldToFilter('status', GuestAlert::STATUS_UNCONFIRMED)
            ->getSize();
    }

    private function findExisting(string $email, int $productId, string $alertType, int $storeId): ?GuestAlert
    {
        $alert = $this->collectionFactory->create()
            ->addFieldToFilter('email', $email)
            ->addFieldToFilter('product_id', $productId)
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('alert_type', $alertType)
            ->setPageSize(1)
            ->getFirstItem();

        return $alert->getId() ? $alert : null;
    }
}
