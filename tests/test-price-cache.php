<?php
/**
 * The catalog price next to another plugin's price filter.
 *
 * The bug these cover (PRO issue #22): the per-request price cache was keyed by product ID alone,
 * so a cached result was returned whatever price it was handed. And the sale badge check removed
 * and re-added our filter, which moved it behind any other callback on the same priority. From then
 * on another plugin's price was replaced with ours, losing its discount or applying it twice.
 *
 * The other plugin here takes 50% off, ours takes 10% off: 45 is right, 50 or 90 means one
 * discount was lost, 22.50 means one was applied twice.
 *
 * @package Taxonomy_Discounts_WooCommerce
 */

$tdw = WC_Taxonomy_Discounts_Webdados();

$tdw_half = function ( $price ) {
	return $price * 0.5;
};

tdw_test_group( 'No rule applies to the product, another plugin halves its price (the client site)' );

tdw_test_reset( array( tdw_test_rule( array( 'active' => false ) ) ) );
$tdw_other = tdw_test_add_other_price_filter( $tdw_half );
tdw_test_price( 'the product page shows the other plugin\'s price', 50, tdw_test_product()->get_price() );
$tdw_product = tdw_test_product();
$tdw_product->is_on_sale();
tdw_test_price( 'and still does after the sale badge check', 50, tdw_test_product()->get_price() );
tdw_test_same( 'and in the price HTML', true, false !== strpos( wp_strip_all_tags( tdw_test_product()->get_price_html() ), wp_strip_all_tags( wc_price( wc_get_price_to_display( tdw_test_product(), array( 'price' => 50 ) ) ) ) ) );

tdw_test_group( 'Our 10% rule applies, another plugin halves the price on the same priority' );

tdw_test_reset( array( tdw_test_rule() ) );
$tdw_other = tdw_test_add_other_price_filter( $tdw_half );
tdw_test_price( 'both discounts apply', 45, tdw_test_product()->get_price() );
tdw_test_product()->is_on_sale();
tdw_test_price( 'both discounts still apply after the sale badge check', 45, tdw_test_product()->get_price() );
tdw_test_same( 'the product is shown as on sale', true, tdw_test_product()->is_on_sale() );

tdw_test_group( 'Our 10% rule applies, another plugin halves the price on a later priority' );

tdw_test_reset( array( tdw_test_rule() ) );
$tdw_other = tdw_test_add_other_price_filter( $tdw_half, 20 );
// The sale badge check runs first here, the way get_price_html() would on a fresh product page
tdw_test_product()->is_on_sale();
tdw_test_price( 'the other discount is not applied twice', 45, tdw_test_product()->get_price() );
tdw_test_price( 'nor on a second read', 45, tdw_test_product()->get_price() );

tdw_test_group( 'The cache is still used for the same product and price' );

tdw_test_reset( array( tdw_test_rule() ) );
$tdw_calls = 0;
$tdw_count = function ( $rule ) use ( &$tdw_calls ) {
	++$tdw_calls;
	return $rule;
};
add_filter( 'tdw_get_product_applied_rule', $tdw_count, 1 );
tdw_test_product()->get_price();
tdw_test_product()->get_price();
tdw_test_product()->get_price();
remove_filter( 'tdw_get_product_applied_rule', $tdw_count, 1 );
tdw_test_same( 'the rule is looked up once for three reads of the same price', 1, $tdw_calls );
tdw_test_price( 'a different incoming price is not served the cached result', 45, $tdw->on_get_price( 50, tdw_test_product() ) );
tdw_test_price( 'the original price still gets its own cached result', 90, $tdw->on_get_price( 100, tdw_test_product() ) );

tdw_test_group( 'Our filter keeps its place on the hook' );

tdw_test_reset( array( tdw_test_rule() ) );
$tdw_other = tdw_test_add_other_price_filter( $tdw_half );
$tdw_ours  = array( $tdw, 'on_get_price' );
tdw_test_same( 'ours runs before the other plugin to start with', true, tdw_test_position( $tdw_ours, 10 ) < tdw_test_position( $tdw_other, 10 ) );
tdw_test_product()->is_on_sale();
tdw_test_same( 'and still does after the sale badge check', true, tdw_test_position( $tdw_ours, 10 ) < tdw_test_position( $tdw_other, 10 ) );
$tdw_cart = tdw_test_cart();
$tdw->cart_remove_price_filters( $tdw_cart );
$tdw->cart_add_price_filters();
tdw_test_same( 'and after the cart calculation steps out of the way and back', true, tdw_test_position( $tdw_ours, 10 ) < tdw_test_position( $tdw_other, 10 ) );

tdw_test_group( 'While the cart is calculated our catalog price stays out of it' );

tdw_test_reset( array( tdw_test_rule() ) );
$tdw->cart_remove_price_filters( $tdw_cart );
tdw_test_price( 'the product price is the undiscounted one', 100, tdw_test_product()->get_price() );
tdw_test_price( 'but a forced calculation still prices the rule', 90, $tdw->on_get_price( 100, tdw_test_product(), true ) );
$tdw->cart_add_price_filters();
tdw_test_price( 'and the catalog discount is back afterwards', 90, tdw_test_product()->get_price() );

tdw_test_group( 'An empty cart does not take our catalog price away' );

tdw_test_reset( array( tdw_test_rule() ) );
$tdw_cart->empty_cart();
$tdw->cart_remove_price_filters( $tdw_cart );
tdw_test_price( 'the catalog discount still applies', 90, tdw_test_product()->get_price() );
$tdw->cart_add_price_filters();

tdw_test_group( 'The cart charges what the product page shows' );

tdw_test_reset( array( tdw_test_rule() ) );
tdw_test_price( 'the product page shows 90', 90, tdw_test_product()->get_price() );
remove_filter( 'tdw_custom_product_loop', '__return_true' );
$tdw_cart = tdw_test_cart();
$tdw_cart->calculate_totals();
tdw_test_price( 'and the cart charges 90', 90, tdw_test_cart_price( $tdw_cart ) );
$tdw_cart->calculate_totals();
tdw_test_price( 'also when the totals are calculated again', 90, tdw_test_cart_price( $tdw_cart ) );
add_filter( 'tdw_custom_product_loop', '__return_true' );
tdw_test_price( 'and the product page still shows 90 afterwards', 90, tdw_test_product()->get_price() );
$tdw_cart->empty_cart();

tdw_test_remove_other_price_filters();
remove_filter( 'tdw_custom_product_loop', '__return_true' );
