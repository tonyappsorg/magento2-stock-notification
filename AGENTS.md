# Tonyapps_StockNotification — notes for AI agents and developers

Read this before changing the module. Workspace-wide rules are in ../AGENTS.md.

## Status

- Package `tonyapps/module-stock-notification` 1.0.0. Built; 2026-10-30 review fixes done.
- Manual QA 2026-11-08: guest subscribe → confirm → back-in-stock email verified end to end; popup layout fixed (`0e321fe`); "My Product Alerts" link never rendered (wrong navigation block name) — fixed with a test.
- 2026-11-11: email template settings added and template IDs renamed for the Email Designer (see Change history).
- Hyvä: deferred 2026-11-08; this module was analysed first. To-do list in ../WORKPLAN.md item 5 (Hyvä variant of `product/subscribe.phtml`, `hyva_catalog_product_view.xml`, `customer/alerts.phtml` data-post → `hyva.postForm()`, Tailwind instead of `_module.less`). Today on Hyvä guests see only core's login link.

## Architecture

Core `Magento_ProductAlert` already stores subscriptions, runs the cron, sends the emails and handles unsubscribes — for logged-in customers. This module does not replace any of that. It adds:

| Feature | How |
|---|---|
| Stock & price subscriptions | Core. Switch on under **Stores > Configuration > Catalog > Catalog > Product Alerts**. |
| Guest subscriptions + pop-up | `tonyapps_stocknotification_guest` table, ajax controller `Controller\Guest\Subscribe` → `Model\GuestSubscriber`; `Block\Product\Subscribe` (`product/subscribe.phtml`, `catalog_product_view.xml`) replaces the two core product-page links. Stock link shown when the product is not available, price link when it is (and the core alert type is allowed). **Double opt-in:** a new guest alert stays unconfirmed (status 2) until the address owner confirms from the email; at most 5 unconfirmed alerts per address (`MAX_UNCONFIRMED_PER_EMAIL`). Confirm and unsubscribe links (`Controller\Guest\Confirm`, `Controller\Guest\Unsubscribe`, `guest/token_action.phtml`, `cacheable="false"`) show a button (GET changes nothing, so mail scanners can't act) and act on POST (plain POST, token-based CSRF). |
| Admin notifications | `Plugin\NotifyAdminOnCoreAlert` on `Magento\ProductAlert\Model\Stock` and `\Price` (new customer subscription, not re-subscribes), guest subscriptions once confirmed, and a daily `Cron\LowStockDigest` reusing the Low Stock report's own collection. Sent by `Model\AdminNotifier` (sender identity `general`). Admin emails render in the frontend area, where the email header/footer exist. |
| Customer account page | `stocknotification/alerts/index` (`Controller\Alerts\Index`, `Block\Customer\Alerts`, `customer/alerts.phtml`, `cacheable="false"`), link "My Product Alerts" in `customer_account_navigation`. Reads the core alert tables, unsubscribes through core's own controllers (data-post). |
| Analytics dashboard | **Catalog > Inventory > Stock Notifications** (`tonyapps_stocknotification/dashboard/index`, `Model\Analytics` view model, `dashboard.phtml`): totals (customer stock alerts, customer price alerts, guests waiting, guests notified, alerts sent), most-wanted products (SKU, product, subscribers, guests, alerts sent), latest guest subscriptions. |

Guest alert sending (`Model\GuestAlertSender::send`): purges first, loads all pending alerts, buckets by store → email → type, skips stores where guest subscriptions are off and types core has disabled, loads each product once per store per run; stock = salable on the store's website; price = `final_price` < price stored at subscribe time (stored price is updated). One email per address per type listing all triggered products (`productsHtml`), sender `general`, prices via `convertAndFormat`.

Other files: `Helper\Data` (config reader), `Model\GuestAlert` (+ resource/collection; `TYPE_STOCK`/`TYPE_PRICE`, `STATUS_PENDING` 0 / `STATUS_NOTIFIED` 1 / `STATUS_UNCONFIRMED` 2), `view/frontend/web/css/source/_module.less`.

Email Designer (`etc/di.xml`, ignored without `Tonyapps_EmailDesigner`): blocks `stocknotification_products` (*Product list*, `{{var productsHtml|raw}}`; guest stock and price alerts) and `stocknotification_low_stock` (*Low stock table*, header row + `{{var rowsHtml|raw}}`; low stock digest); a starter per email in `view/adminhtml/email_designer/` (`guest_stock_alert`, `guest_price_alert`, `new_subscription`, `low_stock`, `guest_confirm` `.mjml`).

### Cron

- `tonyapps_stocknotification_guest_alerts` (`Model\GuestAlertSender::send`) — every 15 min, guest counterpart to core's `product_alert` job. Loads each product once per store per run, and first purges unconfirmed alerts older than 7 days and sent alerts older than 180 days.
- `tonyapps_stocknotification_low_stock` (`Cron\LowStockDigest::execute`) — `0 7 * * *` (07:00 daily).

## Data model

`tonyapps_stocknotification_guest` (guests only; logged-in customers keep core `product_alert_stock` / `product_alert_price`): `entity_id`, `email`, `product_id` (FK `catalog_product_entity` CASCADE), `store_id`, `website_id`, `alert_type` (`stock`|`price`), `price` (at subscribe time), `status` (0 pending, 1 notified, 2 unconfirmed), `unsubscribe_token` (unique, 64), `add_date`, `send_date`, `send_count`. Unique (`email`, `product_id`, `store_id`, `alert_type`); indexes on `status`, `product_id`. `etc/db_schema_whitelist.json` present.

## Configuration and ACL

Section `tonyapps_stocknotification` (**Stores > Configuration > Catalog > Out of Stock Notification**). Guest support and pop-up mode are per-store; admin notification settings and admin templates are global (default scope only).

| Path | Default | Notes |
|---|---|---|
| `general/enabled` | 1 | Enable Extension |
| `general/guest_enabled` | 1 | Allow Guest Subscriptions |
| `general/popup_enabled` | 1 | No = inline email field |
| `admin/recipient_email` | empty | empty → General Contact (`trans_email/ident_general/email`) |
| `admin/notify_new_subscription` | 0 | noisy on busy stores |
| `admin/notify_low_stock` | 1 | same thresholds as Reports > Products > Low Stock |
| `email/<email>_template` | see below | read via `tonyapps_stocknotification/email/%s_template` |

Email template IDs (`<email>` = `guest_confirm`, `guest_stock`, `guest_price`, `admin_subscription`, `admin_low_stock`): `tonyapps_stocknotification_email_<email>_template`, files `guest_confirm.html`, `guest_stock_alert.html`, `guest_price_alert.html`, `new_subscription.html`, `low_stock.html`. Old IDs were `tonyapps_stocknotification_<email>`.

ACL (under `Magento_Catalog::catalog`): `Tonyapps_StockNotification::dashboard` (menu under `Magento_Catalog::inventory`), `Tonyapps_StockNotification::config`.

## Decisions and constraints

- Never replace core ProductAlert; only add what it lacks (guests, admin notices, account page, analytics).
- Guest subscribe only accepts products a shopper could see in that store (enabled, visible, assigned to the store's website); the 5-unconfirmed-per-address cap limits mail to any one address.
- Re-subscribing an unconfirmed alert resends the confirmation at most once an hour per alert (`CONFIRMATION_RESEND_SECONDS` 3600).
- Product page stays full-page cacheable: both variants are rendered and one is picked in the browser from the `customer` customer-data section.
- `ponytail:` ceiling in `GuestAlertSender`: loads all pending alerts at once; add paging if a store parks 100k+.

## Known limitations and open points

- Hyvä storefront not supported yet (see Status).

## Tests

```sh
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/StockNotification/Test/Unit
```

Unit tests: Subscribe block, guest Subscribe controller, Helper, GuestAlertSender, GuestSubscriber, NotifyAdminOnCoreAlert, account navigation layout, layouts. Playwright: `../e2e/tests/stock-notification.spec.js` (helper `../e2e/restock.php`).

## Operations

```sh
bin/magento module:enable Tonyapps_StockNotification
bin/magento setup:upgrade
```

## Change history

- **2026-11-13 Email Designer blocks and starter designs**: `etc/di.xml` registers the *Product list* and *Low stock table* blocks and a starter design for each of the five emails (heading from the subject, the existing texts, the confirm link as a button).
- **2026-11-11 email template settings**: each email's template is chosen in **Stores › Configuration › Catalog › Out of Stock Notification › Email Templates** (guest emails per store view, admin emails globally); a template from **Marketing › Email Templates** or an Email Designer design can be picked. Template IDs follow core's config-path convention so the setting can offer the default: `tonyapps_stocknotification_<email>` are now `tonyapps_stocknotification_email_<email>_template`. Theme overrides keep working (they go by file name). A custom template created from the old ID keeps its content, but its *Template Information* no longer names the default it was loaded from.
- **2026-11-08 QA**: popup layout fixed; "My Product Alerts" navigation link fixed.
- **2026-10-30 review fixes**:
  - **Admin dashboard renders again**: `Model\Analytics` implements `ArgumentInterface` (required for a layout `view_model`), and the empty-table check uses the view model, not the block.
  - **"My Product Alerts" is not full-page cached** (`cacheable="false"`), so it shows the customer's own alerts.
  - **Logged-in customers get core's alert links on the cached product page.** The page renders both variants and picks one in the browser from the `customer` customer-data section; it stays cacheable.
  - **Guest subscribe only accepts products a shopper could see in that store**: enabled, visible, and assigned to the store's website. The 5-unconfirmed-per-address cap still limits mail to any one address.
  - **Re-subscribing an unconfirmed alert resends the confirmation**, at most once an hour per alert.
  - **Guest alert emails show the price in the store's currency** (`convertAndFormat`).
  - Added `etc/db_schema_whitelist.json` and the `magento/module-inventory-low-quantity-notification` composer requirement.
