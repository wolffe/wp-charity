# Validation for 1.2.6

Run from the plugin directory:

```text
php tests/regression.php
php tests/author-notifications.php
php tests/presentation.php
php tests/goal-display.php
node tests/browser-logic.cjs
node --check assets/js/cm-data.js
node --check assets/js/fxm-members-init.js
```

The PHP harness requires PHP 8.0+ with PDO SQLite. It executes the plugin's reporting SQL against an in-memory fixture database, with small WordPress/WooCommerce API doubles. It does not install or simulate a complete WordPress site. SQLite validates the query logic but does not validate MySQL query plans, MySQL SQL modes, or live WooCommerce hook dispatch. The browser-logic tests use a minimal DOM double, not a real browser.

Goal-display checks exercise the production goal renderer and simple donation form with API doubles, including below/exactly/above target, opt-in visibility, restored progress after totals fall, campaign isolation and nonce/capability-protected admin saves. Live WordPress rendering and checkout remain staging checks.

Author-notification tests mock the WooCommerce mailer and Action Scheduler: they never send real mail. They cover direct author selection, multi-campaign isolation, aggregation, currency, eligible statuses, queued cancellation, successful-send markers, failed-recipient retries, exception handling, manual recovery and overlapping worker leases. Live background processing and inbox delivery remain unverified. A true mailer response means acceptance by the configured mail system, not confirmed inbox delivery.

Coverage includes separate whole-order and donation-line calculations, equal-value orders, mixed carts, repeated campaign lines, eligible/excluded statuses, partial and full refunds, HPOS/classic source selection without synchronization, parent rollups, cache invalidation, bounded pages, CSV data, ownership checks, account rendering without order queries, registration encoding, and profile validation.

Compatibility references reviewed:

- [WooCommerce 11.2 release notes](https://developer.woocommerce.com/2026/10/07/woocommerce-11-2-0-release-notes/)
- [WooCommerce HPOS extension guidance](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/)
- [Order item deletion timing](https://developer.woocommerce.com/2026/08/31/changes-to-order-item-deletion/)
- [WordPress 7.1.3 release notes](https://wordpress.org/news/2026/10/wordpress-7-1-3-maintenance-and-security-release/)

The plugin declares compatibility through WooCommerce 11.2.1. The local test harness uses WooCommerce API doubles.

For a future staging check, compare the Campaigns list and Help before/after on the same large-order dataset with both cold and warm caches. Confirm unchanged displayed amounts, then test checkout attribution, order editing/reassignment, status changes, partial/full refunds, deletion/restoration, CSV downloads, signup/login/reset, campaign moderation and profile uploads with HPOS enabled (synchronization off) and classic storage. Check browser navigation and keyboard tabs, including a failed request followed by Retry.

No database migration is required. Versioned caches rebuild on demand. Parent totals are composed from direct campaign summaries at read time so hierarchy changes do not require rebuilding historical donations.
