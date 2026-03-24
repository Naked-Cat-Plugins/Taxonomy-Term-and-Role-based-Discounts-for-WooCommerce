=== Taxonomy/Term and Role based Discounts for WooCommerce ===
Contributors: nakedcatplugins, webdados
Donate link: https://www.paypal.me/Wonderm00n
Tags: deals, sales, marketing, dynamic, pricing
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 8.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Let’s you configure discounts/pricing rules for products based on any product taxonomy terms and WordPress user roles

== Description ==

Let’s you configure discounts/pricing rules for products based on any WooCommerce product taxonomy terms (built-in or custom), straightforwardly.

The discount can be applied for all users, logged-in users, or only for specific WordPress user roles.

Allows you to set a start and end date for each discount.

* Create WooCommerce discounts based on the product category, tag, type, or shipping class.
* Create WooCommerce discounts based on any product attribute.
* Create WooCommerce discounts based on any custom taxonomy, like brand, for example.

* WPML compatible (ability to set discounts on different language terms, not compatible with multi-currency).
* WooCommerce Subscriptions experimental support.

= Discount types =

* Percentage: apply an absolute percentage discount to all the products on a specific taxonomy term;
* Buy x get y free (BOGO): offer y items when x (of the same product) are bought;
* [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) Fixed value: apply an absolute value discount to all the products on a specific taxonomy term;

= Get more with the PRO add-on =

* “Discount Tag” custom taxonomy if you don’t want to use Categories, Tags, or any other existing product taxonomy;
* Sitewide discounts: any discount type applied to all the store products, instead of just one taxonomy term
* Set the maximum amount of free items when using BOGO discounts;
* Replace the sale badge with a discount percentage;
* Replace the sale badge with a discount percentage, even if the discount is not set by a taxonomy but rather by setting a sale price on the product (in beta);
* Show discount information (percentage and dates) on the product loop;
* Show discount information (percentage and dates) on the product single page (mandatory on some legislations, like the Portuguese one, for example);
* Show discount information for non-taxonomy discounts (set by product sale price), including start and end date, thus allowing compliance with legislation, such as Portuguese law, which requires these details to be displayed next to the sale price;
* “Stop - no discount” rule that makes sure products from specific taxonomy terms never have a discount applied, even if there are other rules that will apply for other product taxonomy terms;
* Exclude products already on sale from the discount rule: always, or if taxonomy discount is lower, or if taxonomy discount is higher;
* Set base price for on-sale products: sale price (discount on top of discount) or original regular price (new option);
* Disable shipping methods based on cart items applied rules;
* Set discount rules for non-logged-in users;
* [Google Product Feed for WooCommerce](https://automattic.pxf.io/EEJQd4) integration
* Developer mode - [more info](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin);
* Technical support;
* And more to come...
* [Get it now](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin)

= Notes =

* The discounts are applied on a “per cart line” basis (not to the sum of the products of the same taxonomy);
* Only one rule is applied per cart line, so setting the priorities correctly is very important;
* When aggregating product variations, the quantity will be the sum of the quantities of all the variations, and the discount will be applied to all of them;
* For WPML users, if you want the same discounts to apply to all the languages, you must replicate the rules for each of the translations of the terms
* You can use the `tdw_rule_add`, `tdw_rule_edit`, and `tdw_rule_delete` actions when adding, editing, and deleting rules, to do whatever you want, like, for example, clearing cache ([check out to use them here](https://gist.github.com/webdados/98282475fbee2be347eba45ad81cbba5) and send us cool examples of what you’ve done)

= Other (premium) plugins =

Already know our other WooCommerce (premium) plugins?

* [Advanced Coupon Restrictions for WooCommerce](https://nakedcatplugins.com/product/advanced-coupon-restrictions-for-woocommerce/) - Create coupons for any Product Taxonomy, User details, and Order destination.
* [Simple Checkout Fields Manager for WooCommerce](https://nakedcatplugins.com/product/simple-custom-fields-for-woocommerce-blocks-checkout/) - Add custom fields and manage (remove, make required or optional) core fields on the new WooCommerce Block-based Checkout
* [Simple WooCommerce Order Approval](https://nakedcatplugins.com/product/simple-woocommerce-order-approval/) - The hassle-free solution for WooCommerce order approval before payment
* [Shop as Client for WooCommerce](https://nakedcatplugins.com/product/shop-as-client-for-woocommerce-pro-add-on/) - Quickly create orders on behalf of your customers
* [DPD / SEUR / Geopost Pickup and Lockers network for WooCommerce](https://nakedcatplugins.com/product/dpd-seur-geopost-pickup-and-lockers-network-for-woocommerce/) - Deliver your WooCommerce orders on the DPD and SEUR Pickup network of Parcelshops and Lockers in 21 European countries
* [Taxonomy/Term and Role based Discounts for WooCommerce](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/) - Easily create bulk discount rules for products based on any taxonomy terms (built-in or custom)

Banner by [Arno Senoner](https://unsplash.com/@arnosenoner?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText)

== Installation ==

1. Use the included automatic install feature on your WordPress admin panel and search for “Taxonomy / Term based Discounts for WooCommerce”.
2. Activate the plugin through the `Plugins` menu in WordPress
3. Go to `Products`, `Taxonomy Discounts` to set it up

== Frequently Asked Questions ==

= Why is my product not showing the “Sale” badge? =

We can only show the “sale” badge when we are sure the product will get a discount, no matter how many you buy.
So, the badge is shown only for percentage-based rules with no minimum quantity required.

= How can I display discount information in the product loop and on the page? =

You need to use the following filters:

* `tdw_loop_disc_info_action` and `tdw_loop_disc_info_prio`: to set the hook and priority for the loop page and return, for example, `woocommerce_after_shop_loop_item_title` and `1`
* `tdw_single_disc_info_action` and `tdw_single_disc_info_prio`: to set the hook and priority for the product page and return, for example, `woocommerce_single_product_summary` and `6`
(This is for Storefront. You may have to tweak the action and priority to match your theme better.)

Or you can use the [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin)

= How can I replace the sale badge with the discount percentage? =

This only works for percentage discounts with a minimum quantity of 0 or 1, and you need to add this to your (child-)theme functions.php file:

`add_filter( 'tdw_perc_sale_badge', '__return_true' );`

Or you can use the [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin)

= How can I get the current product or variation price, with the discount applied, outside the loop? =

You can use the `wctd_get_product_current_price` helper function with the product or variation object or ID as the first argument.

You can also pass the quantity as the second argument to calculate percentage discounts with a minimum quantity greater than 1, or “buy x get y free” discounts. The returned price will be the unit price.

This is still beta.

= Will this work on multi-currency shops? =

No. The way we store cart item prices is not currently compatible with stores that allow customers to change currency during the shopping session.

= Is this plugin compatible with the new WooCommerce High-Performance Order Storage? =

Yes.

= Is this plugin compatible with the new WooCommerce block-based Cart and Checkout? =

Yes.

= Can I contribute with a translation? =

Sure. Go to [GlotPress](https://translate.wordpress.org/projects/wp-plugins/taxonomy-discounts-woocommerce) and help us out.

= I need help, can I get technical support? =

This is a free plugin. It’s our way of giving back to the wonderful WordPress community.

Only customers of the [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) get included limited technical support.

There’s a support tab at the top of this page, where you can ask the community for help. We’ll try to keep an eye on the forums, but we cannot promise to answer support tickets.

If you reach us by email or any other direct contact means, we’ll assume you need premium, and of course, paid-for support.

= Where do I report security vulnerabilities found in this plugin? =  
 
You can report any security bugs found in the source code of this plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/taxonomy-discounts-woocommerce). The Patchstack team will assist you with verification, CVE assignment and take care of notifying the developers of this plugin.

== Screenshots ==

1. Apply discounts based on any product taxonomy
2. Set discount rules based on taxonomy/term, role, quantity, and dates. Specify percentage or BOGO discount.
3. PRO add-on settings
4. Show discount information, including dates on the product loop (available with the PRO add-on)
5. Show discount information, including dates on the product page (available with the PRO add-on)

== Changelog ==

= 8.1 - 2026-03-24 =
* [NEW] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 6.2: Add discount to multiple terms at the same time
* [TWEAK] Show discount type description helper when adding a discount
* [FIX] Sitewide discounts from the [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) were not correctly applied to the cart
* [FIX] Show percentage on the product sale badge block
* [FIX] Reset the add new rule form after each interaction
* [DEV] New `tdw_form_add_data_before_save` and `tdw_form_edit_data_before_save` filters to allow the PRO Add-on or 3rd Party developers to manipulate the rule data before it is saved to term meta

= 8.0 - 2026-03-22 =
* [NEW] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 6.0: New discount type: Fixed value
* [NEW] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 6.0: Log and show the last user that edited a rule
* [TWEAK] Show helper for the “Aggregate variations” field
* [TWEAK] Link to each term public archive in the rules table
* [FIX] Subtotal on classic cart was not correct in some scenarios
* [FIX] Discount would not show up for products already in cart in some scenarios
* [FIX] Several fixes regarding variations
* [DEV] Add “Settings and discount configuration” link to the actions on the plugins page
* [DEV] Code cleanup and Improve WordPress Coding Standards
* [DEV] Remove closing slash from void elements
* [DEV] Tested up to WordPress 7.0-beta6-62086 and WooCommerce 10.6.1

= 7.2 - 2026-03-16 =
* [NEW] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 5.1: Improve GitHub Actions and Updater
* [DEV] Tested up to WordPress 7.0-beta3-61865 and WooCommerce 10.6.0-beta.2

= 7.1 - 2026-01-01 =
* [NEW] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 5.0: Show discount information for non-taxonomy discounts (set by product sale price), including start and end date, allowing compliance with legislation, such as the Portuguese law, which requires these details to be displayed next to the sale price - [Start date will be available on WooCommerce 10.5.0](https://github.com/woocommerce/woocommerce/issues/62130#issuecomment-3657559585)
* [NEW] Improve compatibility with [Shop as Client for WooCommerce](https://nakedcatplugins.com/product/shop-as-client-for-woocommerce-pro-add-on/) for WooCommerce: allow changing the cart item price even if a taxonomy discount is applied to it
* [DEV] Allow PRO add-on to filter discount information even when empty, thus allowing showing discount information for non-taxonomy discounts
* [DEV] Remove `load_plugin_textdomain` call as it’s no longer needed
* [DEV] Allow PRO add-on to integrate with [Google Product Feed for WooCommerce](https://automattic.pxf.io/EEJQd4)
* [DEV] Fix plugin version tag
* [DEV] Tested up to WordPress 7.0-alpha-61379 and WooCommerce 10.4.2

= 6.2 - 2025-11-28 =
* [FIX] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 4.0: Delete sitewide discounts was not working
* [FIX] Term dropdown not showing, when adding a new taxonomy discount, after adding a sitewide discount and not reloading the page

= 6.1 - 2025-11-27 =
* [NEW] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 4.0: Sitewide discounts (applied to all the store products)
* [FIX] Hook into `woocommerce_get_variation_sale_price` to return the discounted price
* [FIX] PHP notice introduced in 6.0
* [DEV] Improve WordPress Coding Standards
* [DEV] Tested up to WordPress 6.9-RC2-61293 and WooCommerce 10.4.0-beta.1

= 5.6 - 2025-05-01 =
* [NEW] We are now called Naked Cat Plugins 😻
* [FIX] Comparison between float and integer values was incorrect, causing several problems, including erroneous discount information on percentage discounts
* [FIX] Deprecated: Creation of dynamic property in PHP 8.3
* [DEV] Requires PHP 7.2, WordPress 5.8, and WooCommerce 7.1
* [DEV] Tested up to WordPress 6.8 and WooCommerce 9.8.3

= 5.5 - 2025-03-12 =
* [FIX] Replace `printf` with `sprintf` in several strings to fix incorrect output
* [FIX] Rules applied to user roles not showing on the rules table after editing rule on the backend
* [DEV] Continue applying WordPress Coding Standards

= 5.4 - 2025-03-12 =
* [FIX] Quantities and percentages not showing correctly on the backend
* [FIX] Loading jQuery UI
* [DEV] Continue applying WordPress Coding Standards

= 5.3 - 2025-03-11 =
* [FIX] Fix incorrect URLs
* [DEV] Tested up to WordPress 6.8-alpha-59497 and WooCommerce 9.5.0-rc.1

= 5.2 - 2024-12-09 =
* [DEV] Start applying WordPress Coding Standards
* [FIX] Wrong price on integration with [Feed KuantoKusta for WooCommerce](https://wordpress.org/plugins/feed-kuantokusta-for-woocommerce/)
* [FIX] Small vulnerability only exploitable by logged-in admins and shop managers
* [DEV] Tested up to WordPress 6.8-alpha-59497 and WooCommerce 9.5.0-rc.1

= 5.1 - 2024-10-30 =
* [DEV] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 3.1: License validation on websites with WPML set to have different domains per language
* [FIX] Load text domain at the right time to avoid PHP notices on WordPress 6.7 and above
* [DEV] Tested up to WordPress 6.7-RC2-59324 and WooCommerce 9.4.0-rc.2

= 5.0 - 2024-08-28 =
* [NEW] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 3.0: Two new options to exclude products on sale from the discount rule only if the taxonomy discount is lower or higher than the original discount
* [NEW] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 3.0: If the product is already on sale, and the rule hasn’t been excluded, option to choose if the taxonomy discount should be applied on top of the already discounted price or the original product regular price
* [NEW] Add WooCommerce admin scripts to our settings page so we can implement tooltips on more complex options
* [FIX] Loop in some situations when showing the “on-sale” information, which caused a fatal error
* [TWEAK] Abstract variable product detection with `$product->has_child()` instead of `$product->is_type(‘variable’)` so that all kinds of variable products declared from other plugins are compatible
* [TWEAK] Remove filter in the cart subtotal columns as the values were not accurate. Can be reactivated by passing true to `tdw_cart_item_subtotal_information`
* [DEV] Tested up to WordPress 6.7-alpha-58943 and WooCommerce 9.3.0-beta.1

= 4.6 - 2024-04-23 =
* [FIX] Discount was showing doubled on the archive and single product pages
* [DEV] Tested up to WordPress 6.6-alpha-58011 and WooCommerce 8.9.0-dev

= 4.5 - 2024-04-04 =
* [NEW] Show subtotal price with discount on the classic cart
* [NEW] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 2.3: Improve plugin updater – Show translation update notices
* [FIX] Deprecated: Creation of dynamic property in PHP 8.3
* [FIX] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 2.3: Update cache button click on the backend
* [TWEAK] [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 2.3: Only show license key setting to administrators
* [DEV] Add “Requires Plugins” header
* [DEV] Tested up to WordPress 6.6-alpha-57920 and WooCommerce 8.8.0-rc.1

= 4.4 - 2023-12-12 =
* Declare WooCommerce block-based Cart and Checkout compatibility
* Requires WordPress 5.4
* Tested up to WordPress 6.5-alpha-57159 and WooCommerce 8.4.0-rc.1

= 4.3 - 2023-09-07 =
* Fix a bug getting variation discounts introduced in 4.2

= 4.2 - 2023-09-07 =
* Fix a bug where sometimes variable products would show on sale when they weren’t

= 4.1 - 2023-09-06 =
* [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 2.1: Discount rules database cache for better performance
* Fix version number on readme.txt file
* Small performance tweak on our call to the `woocommerce_product_get_price` filter
* Fix a bug where sometimes products would show on sale when they weren’t

= 4.0 - 2023-09-05 =
* Performance improvement by only loading non-expired and date valid rules on the frontend
* Performance improvement by caching our filter calls to `woocommerce_product_is_on_sale` and `woocommerce_product_get_price` - This can be disabled by passing `false to the new `tdw_enable_cache` filter or on the [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin)
* Tested up to WordPress 6.4-alpha-56479 and WooCommerce 8.1.0-beta.1

= 3.8.1 - 2023-07-11 =
* [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 1.8: Exclude products on sale from the discount rule
* Fix translations
* Requires WooCommerce 5.0 or above
* Tested up to WordPress 6.3-beta3-56192 and WooCommerce 7.9.0-rc.3

= 3.8 - 2023-07-11 =
* [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 1.8: Exclude products on sale from the discount rule
* Requires WooCommerce 5.0 or above
* Tested up to WordPress 6.3-beta3-56192 and WooCommerce 7.9.0-rc.3

= 3.7 - 2023-05-29 =
* Fixed a bug where the incorrect product price would show up on the checkout page
* Tested up to WordPress 6.3-alpha-55859 and WooCommerce 7.8.0-beta.1

= 3.6 - 2022-11-23 =
* [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 1.7: “Stop - no discount” rule that makes sure products from specific taxonomy terms never have a discount applied, even if there are other rules that will apply for other product taxonomy terms
* Code cleanup
* Tested up to WordPress 6.2-alpha-54860 and WooCommerce 7.2.0-beta.1

= 3.5 - 2022-11-22 =
* [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 1.6: Disable shipping methods based on cart items applied rules
* Visual feedback for required fields when adding new rules
* Tested up to WordPress 6.2-alpha-54860 and WooCommerce 7.1

= 3.4 - 2022-11-10 =
* [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 1.5: New rule field “title” that can be used to identify the rule in the admin area as well as being shown alongside the discount information in the frontend
* Tested and confirmed WooCommerce HPOS compatibility
* Fix jQuery deprecations
* Fixed a bug when showing discount information on the product page
* Tested up to WordPress 6.2-alpha-54748 and WooCommerce 7.1

= 3.3.0 - 2022-10-17 =
* [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 1.4: Set maximum amount of free items when using BOGO discounts
* Fixed bug on the dates information when neither start or end date are set

= 3.2.0 - 2022-10-07 =
* Allow [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin) 1.3 to add rules for non logged in users
* Tested up to WordPress 6.1-beta3-54400 and WooCommerce 7.0.0-rc.2

= 3.1.2 - 2022-09-19 =
* Fix readme.txt

= 3.1.1 - 2022-09-19 =
* Fix: Add `$location` to the `tdw_text_x_discount`, `tdw_text_from_x_bought_y_discount` and `tdw_text_for_each_x_bought_y_free` filters

= 3.1 - 2022-09-19 =
* Sale badge (via filter or [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin)) now works in the Flatsome theme
* Remove `strong` tags and replace them with unopinated `span` tags with classes on discount information (via filters or PRO add-on)
* Add `$location` to the `tdw_text_x_discount`, `tdw_text_from_x_bought_y_discount` and `tdw_text_for_each_x_bought_y_free` filters

= 3.0 - 2022-09-18 =
* New [PRO add-on](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/?utm_source=wordpress.org&utm_medium=link&utm_campaign=taxonomydiscounts_woocommerce_plugin)
* Small bug fix on the product single page discount information beta feature
* Deprecated the `WCTD_PERC_SALE_BADGE`, `WCTD_LOOP_DISC_INFO_ACTION`, `WCTD_LOOP_DISC_INFO_PRIO`, `WCTD_PROD_DISC_INFO_ACTION`, `WCTD_PROD_DISC_INFO_PRIO` and `WCTD_ADVANCED_MODE` in favor of the new filters `tdw_perc_sale_badge`, `tdw_loop_disc_info_action`, `tdw_loop_disc_info_prio`, `tdw_single_disc_info_action`, `tdw_single_disc_info_prio` and `tdw_dev_mode`
* Requires WooCommerce 4.0 or above
* Tested up to WordPress 6.1-alpha-53556 and WooCommerce 6.9.2

= 2.1.0 - 2022-06-29 =
* New brand: PT Woo Plugins 🥳
* Code refactoring and simplification when getting the applied rule to a product
* Requires WordPress 5.0, WooCommerce 3.0 and PHP 7.0
* Tested up to WordPress 6.1-alpha-53556 and WooCommerce 6.7.0-beta.2

= 2.0.0 - 2021-05-10 =
* Moved the settings to Products instead of WooCommerce
* Fix WPML compatibility on the admin
* If the `WCTD_ADVANCED_MODE` constant is set to true, a new “ID” field will be available for discount rules, which can be used by developers to identify a specific discount rule
* New `wctd_get_product_applied_rule` helper function to get the product applied rule, if any
* Added [Woocommerce Google Product Feed compatibility](https://automattic.pxf.io/EEJQd4)
* Code refactoring
* Tested up to WordPress 5.8-alpha-50832 and WooCommerce 5.3.0-rc.2
* Relase sponsored by [Planeta Tangerina](https://www.planetatangerina.com/en/) and [SuportesTV.pt](https://suportestv.pt/)

= 1.5.2 - 2021-03-10 =
* Tested up to WordPress 5.8-alpha-50516 and WooCommerce 5.1.0

= 1.5.1 =
* Fix version number on the admin screen
* Technical support clarification
* Tested up to WordPress 5.5-beta4-48649 and WooCommerce 4.3.1

= 1.5.0 =
* Fixed a bug which was causing subscriptions to have an incorrect value
* Process variable subscriptions just like regular variable products (Thanks for the heads up @snap-shot)
* Fix product variations aggregation
* Tested up to WordPress 5.3.3-alpha-46995 and WooCommerce 3.9.0-rc.2

= 1.4.8 =
* Fixed a bug which was causing this discounted prices not to be shown on variable products (Thanks @drosendo)

= 1.4.7 =
* Fixed a bug which was causing the discounted prices not to be shown on the homepage
* Tested up to WordPress 5.3.1-alpha-46771 and WooCommerce 3.8.1

= 1.4.6 =
* Fixed a bug which could cause products not on sale to show the sale badge (Thanks @drosendo)

= 1.4.5 =
* Fixed a bug which was causing PHP Notices (Thanks @drosendo)

= 1.4.4 =
* Tested up to WordPress 5.2.5-alpha and WooCommerce 3.8.0

= 1.4.3 =
* New `tdw_custom_product_loop` that you should return true to inside your product custom loops so that the discounted price shows correctly (Thanks vinha.pt)
* Fix version number on the plugin admin interface
* Tested up to WooCommerce 3.6.3 and WordPress 5.2.1

= 1.4.2 =
* Stop using the WooCommerce term meta helper functions
* Tested up to WooCommerce 3.6.0 RC2 and WordPress 5.1.1

= 1.4.1 =
* Fix: php notice when product prices are set with more decimals than the ones defined on WooCommerce

= 1.4 =
* New `tdw_rule_add`, `tdw_rule_edit` and `tdw_rule_delete` actions when adding, editing or deleting rules (by @onlylowercaselettersandnumbers suggestion)
* Tested up to WooCommerce 3.5.4 and WordPress 5.1

= 1.3 =
* New `wctd_get_product_current_price` helper function that developers can use to get the current product or variation price with the discount applied
* Better plugin initialization
* Minor code cleanup
* Tweaks on the admin page
* Fixed `WC tested up to` tag

= 1.2 =
* Beta: If you set the `WCTD_PERC_SALE_BADGE` constant to true, the sale badge will be replaced by the discount percentage, if the minimum quantity is 0 or 1
* Fix: when percentage discount was set for a minimum quantity of 1 and the discount was not shown on archives and single product page
* Fix: when the product had no price a php warning was thrown
* Tested up to WooCommerce 3.5.4 and WordPress 5.1 (beta)

= 1.1 =
* It’s now possible to set rules for all users, logged-in users or users belonging to specific user roles (sponsored by Amaranto Design)
* Better code indentation/standards
* If you set an integer value on the `WCTD_GET_PRICE_FILTER_PRIO` constant, that priority will be used on the `woocommerce_product_get_price` filter

= 1.0 =
* Now correctly shows the discount inside WooCommerce Product Shortcodes (sponsored by Amaranto Design)
* Small admin UX tweaks
* Tested up to WooCommerce 3.5.1 and bumped `WC tested up to` tag
* Reached 1.0 for no special reason :-)

= 0.9.8 =
* Use `add_woocommerce_term_meta` and `update_woocommerce_term_meta` instead of `add_term_meta` and `update_term_meta`
* Bumped `WC tested up to` tag
* Bumped `Requires at least` tag

= 0.9.7 =
* Added the taxonomy internal name on the select field
* Bumped `WC tested up to` tag

= 0.9.6 =
* “Feed KuantoKusta for WooCommerce” (to be released) plugin integration fix

= 0.9.5 =
* Fix: some variation discounts were not applied correctly
* “Feed KuantoKusta for WooCommerce” (to be released) plugin integration

= 0.9 =
* Fix: after calculations, round the discounted price using the default WooCommerce decimal places, in order to avoid totals miscalculations
* Support for start and end date/time activated by default (no need to use the `WCTD_ENABLE_TIME` constant)

= 0.8.1 =
* Tested up to WooCommerce 3.3
* Bumped `Tested up to` tag

= 0.8 =
* Fixed a bug where the end date of a discount would not be taken in account because 00:00:00 was assumed instead of 23:59:59;
* Experimental support for start and end date/time (you must define `WCTD_ENABLE_TIME` as true on your wp-config.php file for this feature to be enabled);

= 0.7.4 =
* Removed the translation files from the plugin `languages` folder (the translations are now managed on WordPress.org’s GlotPress tool and will be automatically downloaded from there)
* Tested up to WooCommerce 3.2
* Added `WC tested up to` tag on the plugin main file
* Bumped `Tested up to` tag

= 0.7.3 =
* Fixed a bug where some “Buy x get y free” discounts would not be calculated correctly
* Bumped `Tested up to` tag

= 0.7.2 =
* Fixed a bug that would prevent ajax based backend actions to work correctly
* Fixed a (nasty) bug that would duplicate discounts each time the cart was loaded on WooComerce 3.0 and above

= 0.7.1 =
* Fixed a bug that would prevent ajax based frontends to apply discounts
* Fixed a bug where the sale price wouldn’t correctly set on WooCommerce 3.0 cart
* Beta: show sale flash on variable products and sale price on variations (after choosen on the product page)
* Beta: show discount information on the loop and product pages (see the FAQ)

= 0.7 =
* Tested and adapted to work with WooCommerce 3.0.0-rc.2
* Bumped `Tested up to` tag

= 0.6.2.1 =
* Bumped `Tested up to` tag

= 0.6.2 =
* Fix version number;

= 0.6.1 =
* Fix to avoid php notices when old rules don’t have the new “aggregate product variations” setting setup;

= 0.6 =
* New option on tjhe percentage discounts that allow to aggregate different product variations on the cart and count them all as if they were a single product, so that the discount will be applied to all of them;

= 0.5 =
* Increase compatibility with other plugins that manipulate the product value;
* Stop using the $woocommerce global;
* Tested up to WordPress 4.6.1;

= 0.4 =
* New `wctd_get_product_ids_on_sale` function to get the product_id of all the products that have an active discount, similar to WooCommerce’s native `wc_get_product_ids_on_sale` (to be used by developers);
* Tested up to WordPress 4.5;

= 0.3 =
* First public release;
* Minimum quantity on percentage discounts (leave empty or zero to apply to any quantity);
* Configuration screen changes for better UX;

= 0.2 =
* It’s now possible to disable further coupon discounts on top of our discounts, on a per rule basis:
* Fixed “Cart Discount” will not be allowed if any discounted product is in the cart, because WooCommerce distributes the fixed value over the several cart lines and the final discount would not be the total coupon value, which would not be very clear for the customer;
* The other coupon types, like “Cart % Discount”, “Product Discount” and “Product % Discount” will be applied only on cart lines where there’s no discounted products (that have a rule where “Disable coupons” is activated);

= 0.1 =
* First (non-public) release;