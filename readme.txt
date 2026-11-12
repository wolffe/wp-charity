=== WP Charity for WooCommerce ===
Contributors: butterflymedia
Tags: charity, donations, fundraising, woocommerce, campaigns, nonprofit
Requires at least: 7.1
Tested up to: 7.1.3
Requires PHP: 7.4
Stable tag: 1.2.6
Requires Plugins: woocommerce
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Raise money for your cause with campaigns, peer-style volunteer fundraisers, and WooCommerce-powered donations.

== Description ==

**WP Charity for WooCommerce** helps non-profits and charities run fundraising campaigns on their own WordPress site. Donations flow through **WooCommerce** orders, so you keep your existing gateways, taxes, emails, and reporting.

**Highlights**

* Custom **Campaign** post type with goals, dates, media, and optional parent/child (volunteer) campaigns.
* Donation forms tied to campaigns via WooCommerce products and cart/checkout line meta.
* **Member account** area for volunteers: campaigns, new campaign form, donations to their campaigns, profile.
* Admin settings for volunteer campaign moderation, notification emails, and optional webhooks (e.g. GoHighLevel).
* Shortcodes for campaign UI on any page.

Product information and documentation: [WP Charity on 4Property](https://4property.com/wordpress-plugins/wp-charity-wordpress-donation-plugin-fundraising/).

== Installation ==

1. Install and activate **WooCommerce**.
2. Upload the plugin folder to `/wp-content/plugins/` or install via the Plugins screen.
3. Activate **WP Charity for WooCommerce**.
4. In **WP Charity** settings, set the member account page, notification options, and optional webhook URLs.
5. Create campaigns under **WP Charity** in the admin menu and assign a donation product per campaign where needed.

== Frequently Asked Questions ==

= Does this work without WooCommerce? =

No. WooCommerce is required for checkout, orders, and donation amounts.

= Can volunteers create campaigns without using wp-admin? =

Yes. Volunteers use the front-end account page; they are not intended to use the WordPress dashboard for posts or pages.

= Where do I see donations per campaign? =

Orders store a campaign ID on the relevant line item. The admin donations report and campaign list include campaign-related totals where implemented.

== Screenshots ==

1. Campaign settings and donation product assignment in the editor.
2. Member account tabs: dashboard, campaigns, donations, new campaign, profile.
3. WooCommerce order screen: assign or reassign a line item to a campaign.

== Changelog ==

= 1.2.6 =
* **Campaign goals:** Optional "Hide goal progress once the target is reached" setting in the admin and volunteer campaign editors hides the target, percentage, progress bar and success message once funded, keeping the amount raised and donation controls visible. Existing campaigns retain their current display by default.

= 1.2.5 =
* **Campaign videos:** Campaign stories now preserve WordPress block, oEmbed and shortcode rendering so YouTube blocks display correctly, with responsive video sizing.
* **Compatibility:** WordPress 7.1 is now the minimum supported version. Tested up to WooCommerce 11.2.1.

= 1.2.4 =
* **Recent donations:** Donation rows now show only the donated amount and date, without WooCommerce processing or completion statuses.

= 1.2.3 =
* **Campaign list:** A dedicated Campaign type column uses compact coloured badges for parent campaigns, volunteer campaigns and campaigns that allow volunteer fundraising.

= 1.2.2 =
* **Campaign pages:** Modern responsive presentation for parent and volunteer campaigns, with improved media, story, donation, progress, date, sharing and recent-donation sections.
* **Admin campaigns list:** Coloured badges identify parent campaigns and campaigns that allow volunteer fundraising.
* **Accessibility:** Improved keyboard focus, reduced-motion handling and fundraising progress semantics.

= 1.2.1 =
* **Author donation emails:** Automatically notify the authors of campaigns attached to an order when it enters Processing, Completed or On hold. One separate message per author/order contains only their campaign amounts. On-hold messages explain that payment awaits confirmation.
* **Delivery:** Messages run through WooCommerce's background scheduler and mailer. Successful recipients are recorded using HPOS-compatible order metadata; failed recipients get up to three attempts. Order actions can retry failures without resending successful messages. Existing orders are not bulk-emailed on upgrade.

= 1.2.0 =
* **Performance:** Campaign totals use batched, cached database aggregates against the active WooCommerce order store. Existing amounts, statuses and refund treatment are preserved.
* **Reports:** Help retains its campaign report, with 20 campaigns per page and orders loaded on request. Reports and account donations are paginated; full CSV exports stream in batches.
* **Account:** Donations load only when opened. Registration safely sends passwords and phone numbers, profile updates change the actual account email, and campaign editing checks ownership and permissions. Modern vanilla JavaScript handles forms and accessible tabs.
* **How To:** New settings tab with practical questions and answers grounded in the plugin's features.
* **Fixes:** Automatic campaign approval honours its setting; donation reassignment uses valid, separate forms in the WooCommerce order editor.
* **Compatibility:** Updated compatibility metadata for WordPress 7.1.3 and WooCommerce 11.2.0. This release has isolated regression and syntax checks; live integration and performance timing were not performed. See tests/README.md.

= 1.1.0 =
* **Emails:** Author receives an email when a moderated (draft) campaign is submitted, and when it is approved and published (with a link to the live campaign).
* **Integrations:** Optional **GHL Campaign Approval Webhook URL** setting; JSON POST on draft → publish with donor context fields.
* **Account:** **Donations** tab listing donations to the volunteer’s campaigns (donor, email, amount, campaign link) in a responsive grid.
* **Orders:** Sidebar meta box **Donation campaign** to assign or reassign any order line to a campaign; campaign dropdown shows ID for duplicate titles; supports orders without prior `_campaign_id`.
* **Admin campaigns list:** **Total donated** column; for parent campaigns that allow volunteers, total includes all child campaign donations (line-item sums).
* **Volunteers:** **Remind admin to review** link for draft campaigns (resends admin pending-review email); success notice after use.
* **Security:** Campaign CPT uses `campaign` capability type; volunteer role has campaign-only caps (no `edit_posts` / pages); volunteers redirected away from `wp-admin` with login redirect to the account page; profile updates restricted to the logged-in user; profile picture uploads validated as images only (server-side, plus `accept="image/*"`).
* **Fix:** Status bypass for admins/editors in `cm_prevent_status_change` now uses real capabilities (`manage_options` / `edit_others_campaigns`) instead of an invalid `administrator` capability check.
* **Meta:** Plugin URI and compatibility headers updated; WooCommerce tested up to **10.6.2**; WordPress tested up to **7.0**.

= 1.0.9 =
* Earlier public release (see plugin history in repository if needed).

== Upgrade Notice ==

= 1.1.0 =
Volunteer capabilities and the campaign post type capabilities changed: run through your staging site first. Administrators and Editors receive full campaign caps automatically; custom roles that previously relied on `edit_posts` for campaigns may need manual capability grants.
