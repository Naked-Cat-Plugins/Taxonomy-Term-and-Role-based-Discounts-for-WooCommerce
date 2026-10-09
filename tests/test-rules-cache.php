<?php
/**
 * Which rules the front end loads.
 *
 * The bug these cover (PRO issue #22): when the PRO add-on's rules database cache was on, the front
 * end took it as is, inactive, expired and other roles' rules included, while the uncached path
 * leaves those out. Any loaded rule switches the catalog price cache on, so deactivating the only
 * rule did not take us out of the way.
 *
 * Also: the uncached path left an empty priority behind for an expired or other role rule, so the
 * rules still counted as present.
 *
 * @package Taxonomy_Discounts_WooCommerce
 */

$tdw = WC_Taxonomy_Discounts_Webdados();

/**
 * Every rule in a get_discount_rules() result, flattened.
 *
 * @param array $rules Rules by priority and term.
 * @return array
 */
function tdw_test_flatten_rules( $rules ) {
	$flat = array();
	foreach ( (array) $rules as $terms ) {
		foreach ( (array) $terms as $term_rules ) {
			foreach ( (array) $term_rules as $rule ) {
				$flat[] = $rule;
			}
		}
	}
	return $flat;
}

/**
 * Whether a get_discount_rules() result has a priority or term holding no rules.
 *
 * @param array $rules Rules by priority and term.
 * @return bool
 */
function tdw_test_has_empty_bucket( $rules ) {
	foreach ( (array) $rules as $terms ) {
		if ( empty( $terms ) ) {
			return true;
		}
		foreach ( (array) $terms as $term_rules ) {
			if ( empty( $term_rules ) ) {
				return true;
			}
		}
	}
	return false;
}

$tdw_previous_user = get_current_user_id();
wp_set_current_user( 0 );

tdw_test_group( 'Rules served from the PRO add-on\'s rules database cache' );

$tdw_cached      = tdw_test_rules_structure(
	array(
		tdw_test_rule(
			array(
				'priority' => 0,
				'meta_id'  => 1,
			)
		),
		tdw_test_rule(
			array(
				'priority' => 1,
				'meta_id'  => 2,
				'active'   => false,
			)
		),
		tdw_test_rule(
			array(
				'priority' => 2,
				'meta_id'  => 3,
				'from'     => '2026-09-01',
				'to'       => '2026-09-30',
			)
		),
		tdw_test_rule(
			array(
				'priority'  => 3,
				'meta_id'   => 4,
				'user_role' => 'administrator',
			)
		),
	)
);
$tdw_serve_cache = function () use ( &$tdw_cached ) {
	return $tdw_cached;
};
add_filter( 'tdw_discount_rules_database_cache', $tdw_serve_cache, 99 );

tdw_test_reset( false );
$tdw_front = $tdw->get_discount_rules( true );
tdw_test_same(
	'only the active, current rule for this visitor is loaded',
	array( 1 ),
	array_map(
		function ( $rule ) {
			return $rule['meta_id'];
		},
		tdw_test_flatten_rules( $tdw_front )
	)
);
tdw_test_same( 'no empty priority is left behind', false, tdw_test_has_empty_bucket( $tdw_front ) );
tdw_test_price( 'and it still discounts the product', 90, tdw_test_product()->get_price() );

tdw_test_group( 'The only cached rule was deactivated' );

$tdw_cached = tdw_test_rules_structure( array( tdw_test_rule( array( 'active' => false ) ) ) );
tdw_test_reset( false );
tdw_test_same( 'no rules are loaded on the front end', 0, count( $tdw->get_discount_rules( true ) ) );
tdw_test_price( 'the product is at its own price', 100, tdw_test_product()->get_price() );
tdw_test_same( 'and the price was not cached, we are out of the way', array(), $tdw->cache_on_get_price );

remove_filter( 'tdw_discount_rules_database_cache', $tdw_serve_cache, 99 );

tdw_test_group( 'Rules read from the database, without the cache' );

$tdw_fixture    = tdw_test_fixture();
$tdw_expired_id = add_term_meta(
	$tdw_fixture['term_id'],
	$tdw->discount_rule_meta_key,
	array(
		'type'      => 'percentage',
		'value'     => 10,
		'min-qtt'   => 0,
		'aggr-var'  => false,
		'user_role' => '_all_users_',
		'priority'  => 987654,
		'active'    => true,
		'taxonomy'  => 'product_cat',
		'from'      => '2026-09-01',
		'to'        => '2026-09-30',
	)
);
$tdw_no_cache   = function () {
	return false;
};
add_filter( 'tdw_discount_rules_database_cache', $tdw_no_cache, 99 );
tdw_test_reset( false );
$tdw_front = $tdw->get_discount_rules( true );
tdw_test_same( 'an expired rule leaves no empty priority behind', false, isset( $tdw_front[987654] ) );
tdw_test_same( 'nor does any other skipped rule', false, tdw_test_has_empty_bucket( $tdw_front ) );
tdw_test_reset( false );
$tdw_back = $tdw->get_discount_rules();
tdw_test_same( 'the admin still sees the expired rule', true, isset( $tdw_back[987654][ $tdw_fixture['term_id'] ][0] ) );
remove_filter( 'tdw_discount_rules_database_cache', $tdw_no_cache, 99 );
delete_metadata_by_mid( 'term', $tdw_expired_id );

wp_set_current_user( $tdw_previous_user );
remove_filter( 'tdw_custom_product_loop', '__return_true' );
