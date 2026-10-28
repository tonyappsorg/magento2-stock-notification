# Tonyapps_StockNotification

Out of stock / price drop notifications for Magento 2.

Core `Magento_ProductAlert` already stores subscriptions, runs the cron, sends the emails
and handles unsubscribes — for logged-in customers. This module does not replace any of
that. It adds the five things core is missing:

| Feature | How |
|---|---|
| Stock & price subscriptions | Core. Switch on under **Stores > Configuration > Catalog > Catalog > Product Alerts**. |
| Guest subscriptions + pop-up | `tonyapps_stocknotification_guest` table, ajax controller, `Block\Product\Subscribe` replaces the two core product-page links. **Double opt-in:** a new guest alert stays unconfirmed (status 2) until the address owner confirms from the email; at most 5 unconfirmed alerts per address. Confirm and unsubscribe links show a button (GET changes nothing, so mail scanners can't act) and act on POST. |
| Admin notifications | `NotifyAdminOnCoreAlert` plugin (new customer subscription, not re-subscribes), guest subscriptions once confirmed, and a daily `LowStockDigest` cron reusing the Low Stock report's own collection. Admin emails render in the frontend area, where the email header/footer exist. |
| Customer account page | `stocknotification/alerts/index`, reads the core alert tables, unsubscribes through core's own controllers. |
| Analytics dashboard | **Catalog > Inventory > Stock Notifications** — totals, most-wanted products, latest guest signups. |

## Cron

* `tonyapps_stocknotification_guest_alerts` — every 15 min, guest counterpart to core's `product_alert` job. Loads each product once per store per run, and first purges unconfirmed alerts older than 7 days and sent alerts older than 180 days.
* `tonyapps_stocknotification_low_stock` — 07:00 daily.

## Configuration

**Stores > Configuration > Catalog > Out of Stock Notification**. Guest support and pop-up
mode are per-store; admin notification settings are global.

## Install

```
bin/magento module:enable Tonyapps_StockNotification
bin/magento setup:upgrade
```

## Tests

```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/StockNotification/Test/Unit
```
