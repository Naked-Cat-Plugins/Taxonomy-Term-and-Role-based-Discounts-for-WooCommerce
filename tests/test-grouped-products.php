<?php
/**
 * The sale badge check on grouped products.
 *
 * The bug this covers: a grouped product has children but no variation prices, and the sale badge
 * check asked it for them, a fatal error on any page showing a grouped product that was not
 * already on sale, the block cart page's product grid among them.
 *
 * WooCommerce already asks each child of a grouped product whether it is on sale, and each child's
 * answer goes through our filter, so a child discounted by a rule makes the grouped product on sale.
 *
 * @package Taxonomy_Discounts_WooCommerce
 */

$tdw_grouped = new WC_Product_Grouped();
$tdw_grouped->set_name( 'TDW test grouped product' );
$tdw_grouped->set_status( 'publish' );
$tdw_grouped->set_children( array( tdw_test_fixture()['product_id'] ) );
$tdw_grouped->save();
$tdw_grouped_id = $tdw_grouped->get_id();

tdw_test_group( 'A grouped product whose child no rule discounts' );

tdw_test_reset( array( tdw_test_rule( array( 'active' => false ) ) ) );
try {
	tdw_test_same( 'is not on sale, and checking does not crash', false, wc_get_product( $tdw_grouped_id )->is_on_sale() );
} catch ( Throwable $e ) {
	tdw_test_assert( 'is not on sale, and checking does not crash', false, $e->getMessage() );
}

tdw_test_group( 'A grouped product whose child a rule discounts' );

tdw_test_reset( array( tdw_test_rule() ) );
try {
	tdw_test_same( 'is on sale, through its child', true, wc_get_product( $tdw_grouped_id )->is_on_sale() );
} catch ( Throwable $e ) {
	tdw_test_assert( 'is on sale, through its child', false, $e->getMessage() );
}

wp_delete_post( $tdw_grouped_id, true );
remove_filter( 'tdw_custom_product_loop', '__return_true' );
