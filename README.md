# Out of Stock & Price Drop Notifications for Magento 2

Let every shopper, signed in or not, ask to be emailed when a product is back in stock or its price drops, and see what they're waiting for.

Magento Open Source can already send back-in-stock and price-drop alerts, but only to signed-in customers: guests are sent to the login page, store owners never hear about demand, and customers have no page listing their alerts. This module keeps core's alerts and adds guest subscriptions, admin notifications, a "My Product Alerts" account page and a demand dashboard.

## Features

- **Guest subscriptions**: guests leave an email address on the product page, in a pop-up or inline, instead of being sent to sign in. Signed-in customers keep using Magento's own alerts.
- **Double opt-in**: a guest alert only becomes active after the address owner confirms it from an email, so nobody can sign someone else up. Unconfirmed sign-ups are capped per address and cleaned up after a week.
- **Back-in-stock and price-drop emails for guests**: sent when the product can be bought again, or when its price falls below the price at sign-up, with prices in the store's currency. Several products for the same shopper go in one email.
- **One-click-safe links**: confirm and unsubscribe links show a button first, so mail scanners can't act on them.
- **"My Product Alerts" page**: signed-in customers see all their stock and price alerts in their account and can unsubscribe from each.
- **Admin notifications**: optional email on every new subscription, and a daily low-stock digest using the same thresholds as Magento's Low Stock report.
- **Demand dashboard**: **Catalog › Inventory › Stock Notifications** shows totals, the most-wanted products (your restock shortlist) and the latest guest sign-ups.
- **Full-page cache friendly**: the product page stays cacheable and still shows the right subscribe option to guests and signed-in customers.
- **Every email is editable**: pick your own template or an Email Designer design for each of the five emails.

## How it works

1. Switch on Magento's product alerts (**Stores › Configuration › Catalog › Catalog › Product Alerts**).
2. On a product page (a back-in-stock alert when it's out of stock, a price alert when it's available), a guest enters an email address and confirms the subscription from the email they receive.
3. Every 15 minutes, cron checks guest subscriptions and emails those whose product is back in stock or cheaper. Magento's own cron does the same for signed-in customers.
4. You follow demand on the dashboard, in optional new-subscription emails and in the daily low-stock digest.

## Good to know

- Builds on Magento's Product Alerts: they must be switched on for alerts to be sent.
- Guest alerts are confirmed by email first; at most 5 unconfirmed alerts per address. Unconfirmed alerts expire after 7 days; sent alerts are removed after 180 days.
- Guests can only subscribe to products that are enabled, visible and assigned to the store's website.
- Admin notification settings apply to all stores; guest and pop-up settings can differ per store view.
- Requires Magento cron to be running.
- Built and tested on the Luma theme. On Hyvä, guests currently see only Magento's login link.

## Requirements

- Magento Open Source 2.4.8
- PHP 8.3 or 8.4
- Magento modules: ProductAlert, InventoryLowQuantityNotification

## Installation

```sh
composer require tonyapps/module-stock-notification
bin/magento module:enable Tonyapps_StockNotification
bin/magento setup:upgrade
```

Make sure Magento cron is running.

## Configuration

- **Stores › Configuration › Catalog › Catalog › Product Alerts**: switch on stock and price alerts (Magento core).
- **Stores › Configuration › Catalog › Out of Stock Notification**: enable, guest subscriptions, pop-up or inline form, admin notifications and email templates.
- **Catalog › Inventory › Stock Notifications**: the demand dashboard.

### Redesigning the emails

Each email's template is chosen under **Stores › Configuration › Catalog › Out of Stock Notification › Email Templates** (guest emails per store view, admin emails for all stores). Pick a template made in **Marketing › Email Templates** or a design made with the [Email Designer](https://github.com/tonyappsorg/magento2-email-designer) (`Tonyapps_EmailDesigner`) (**Marketing › Communications › Email Designer**). The default template is used until another one is picked.

## For developers

Architecture, decisions, data model and tests are in [AGENTS.md](AGENTS.md).
