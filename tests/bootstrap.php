<?php
/**
 * Test bootstrap.
 *
 * These are integration tests: what they assert is how our price filter behaves next to other
 * callbacks on WooCommerce's own price hooks, which a stub cannot reproduce. So they boot a real
 * WordPress through wp-load.php, with WooCommerce and this plugin active.
 *
 * No Composer, no PHPUnit, no WordPress test suite. Run `php tests/run.php` from the plugin root.
 *
 * Every test file works on a throwaway category and simple product created here and deleted at
 * shutdown. Rules are injected straight into the per-request rules property, so the rules stored on
 * the install are never read or changed by the price tests.
 *
 * Point WP_ROOT at the WordPress directory, or let it walk up from here, which works for a plugin
 * living inside a normal wp-content/plugins tree.
 *
 * @package Taxonomy_Discounts_WooCommerce
 */

$tdw_test_root = getenv( 'WP_ROOT' );
if ( ! $tdw_test_root ) {
	$tdw_test_root = dirname( __DIR__, 4 );
}
$tdw_test_root = rtrim( $tdw_test_root, '/' );

if ( ! file_exists( $tdw_test_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Could not find wp-load.php in {$tdw_test_root}.\nSet WP_ROOT to the WordPress directory.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $tdw_test_root . '/wp-load.php';

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce is not active on this install; these tests need it.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}
if ( ! function_exists( 'WC_Taxonomy_Discounts_Webdados' ) ) {
	fwrite( STDERR, "Taxonomy/Term and Role-based Discounts for WooCommerce is not active on this install.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

// --- the smallest assertion API that does the job -----------------------------------------------

$GLOBALS['tdw_test_results'] = array(
	'passed' => 0,
	'failed' => 0,
);

/**
 * Name the group of assertions that follow.
 *
 * @param string $name Group name.
 */
function tdw_test_group( $name ) {
	echo "\n  " . $name . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Assert.
 *
 * @param string $what What is being asserted, phrased as the expected behaviour.
 * @param bool   $ok   Whether it held.
 * @param mixed  $got  Printed when it did not.
 */
function tdw_test_assert( $what, $ok, $got = null ) {
	if ( $ok ) {
		++$GLOBALS['tdw_test_results']['passed'];
		echo "    \033[32mok\033[0m   " . $what . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}
	++$GLOBALS['tdw_test_results']['failed'];
	echo "    \033[31mFAIL\033[0m " . $what . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( null !== $got ) {
		echo '         got: ' . wp_json_encode( $got ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Assert two prices are equal, to the cent.
 *
 * @param string $what     What is being asserted.
 * @param float  $expected Expected.
 * @param mixed  $actual   Actual.
 */
function tdw_test_price( $what, $expected, $actual ) {
	$ok = is_numeric( $actual ) && abs( (float) $expected - (float) $actual ) < 0.005;
	tdw_test_assert(
		$what,
		$ok,
		$ok ? null : array(
			'expected' => $expected,
			'actual'   => $actual,
		)
	);
}

/**
 * Assert two values are identical.
 *
 * @param string $what     What is being asserted.
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 */
function tdw_test_same( $what, $expected, $actual ) {
	$ok = ( $expected === $actual );
	tdw_test_assert(
		$what,
		$ok,
		$ok ? null : array(
			'expected' => $expected,
			'actual'   => $actual,
		)
	);
}

// --- fixtures ------------------------------------------------------------------------------------

/**
 * A throwaway category and a simple product in it, priced 100, created once per run.
 *
 * @return array { term_id, product_id }
 * @throws Exception When the category cannot be created.
 */
function tdw_test_fixture() {
	static $fixture = null;
	if ( null === $fixture ) {
		$term = wp_insert_term( 'TDW test category ' . wp_generate_password( 6, false ), 'product_cat' );
		if ( is_wp_error( $term ) ) {
			throw new Exception( 'Could not create the test category: ' . $term->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$product = new WC_Product_Simple();
		$product->set_name( 'TDW test product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '100' );
		$product->set_category_ids( array( $term['term_id'] ) );
		$product->save();
		$fixture = array(
			'term_id'    => (int) $term['term_id'],
			'product_id' => (int) $product->get_id(),
		);
		register_shutdown_function(
			function () use ( $fixture ) {
				wp_delete_post( $fixture['product_id'], true );
				wp_delete_term( $fixture['term_id'], 'product_cat' );
			}
		);
	}
	return $fixture;
}

/**
 * A rule as get_discount_rules() returns it, on the test category unless told otherwise.
 *
 * @param array $overrides Keys to change.
 * @return array
 */
function tdw_test_rule( $overrides = array() ) {
	$fixture = tdw_test_fixture();
	return array_merge(
		array(
			'type'                    => 'percentage',
			'value'                   => 10,
			'min-qtt'                 => 0,
			'aggr-var'                => false,
			'user_role'               => '_all_users_',
			'priority'                => 0,
			'active'                  => true,
			'disable_coupon'          => false,
			'taxonomy'                => 'product_cat',
			'from'                    => '',
			'to'                      => '',
			'term_id'                 => $fixture['term_id'],
			'meta_id'                 => 999999,
			'allows-discount-to-zero' => false,
		),
		$overrides
	);
}

/**
 * Wrap rules in the priority / term structure get_discount_rules() returns.
 *
 * @param array $rules Rules.
 * @return array
 */
function tdw_test_rules_structure( $rules ) {
	$structure = array();
	foreach ( $rules as $rule ) {
		$structure[ $rule['priority'] ][ $rule['term_id'] ][] = $rule;
	}
	ksort( $structure );
	return $structure;
}

/**
 * Start a test from a clean slate: as if this were a new page load on a product page.
 *
 * @param array|false $rules Rules to load for the front end, false to leave them to the database.
 */
function tdw_test_reset( $rules ) {
	$tdw                          = WC_Taxonomy_Discounts_Webdados();
	$tdw->cache_on_get_price      = array();
	$tdw->cache_on_sale           = array();
	$tdw->discount_rules_frontend = ( false === $rules ) ? false : tdw_test_rules_structure( $rules );
	$tdw->discount_rules_backend  = false;
	$tdw->enable_cache            = true;
	if ( property_exists( $tdw, 'price_filter_paused' ) ) {
		$tdw->price_filter_paused = false;
	}
	// Put our filter back where it is registered on a fresh page load, first among what was hooked at plugin load
	tdw_test_remove_other_price_filters();
	remove_filter( $tdw->get_price_filter, array( $tdw, 'on_get_price' ), $tdw->get_price_filter_priority );
	tdw_test_hook_first( $tdw->get_price_filter, array( $tdw, 'on_get_price' ), $tdw->get_price_filter_priority, 2 );
	// A product page
	add_filter( 'tdw_custom_product_loop', '__return_true' );
}

/**
 * Hook a callback ahead of everything else already on its priority.
 *
 * @param string   $hook          Hook.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Accepted arguments.
 */
function tdw_test_hook_first( $hook, $callback, $priority, $accepted_args ) {
	global $wp_filter;
	add_filter( $hook, $callback, $priority, $accepted_args );
	$idx       = _wp_filter_build_unique_id( $hook, $callback, $priority );
	$callbacks = $wp_filter[ $hook ]->callbacks[ $priority ];
	$ours      = array( $idx => $callbacks[ $idx ] );
	unset( $callbacks[ $idx ] );
	$wp_filter[ $hook ]->callbacks[ $priority ] = $ours + $callbacks; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
}

/**
 * Another plugin's price filter, registered after ours, like the client site where the bug was found.
 * Only touches the test product.
 *
 * @param callable $change   Gets the price, returns the new one.
 * @param int      $priority Priority.
 * @return callable The filter, to look it up on the hook.
 */
function tdw_test_add_other_price_filter( $change, $priority = 10 ) {
	$fixture  = tdw_test_fixture();
	$callback = function ( $price, $product ) use ( $change, $fixture ) {
		if ( (int) $product->get_id() !== $fixture['product_id'] || ! is_numeric( $price ) ) {
			return $price;
		}
		return $change( (float) $price );
	};
	add_filter( WC_Taxonomy_Discounts_Webdados()->get_price_filter, $callback, $priority, 2 );
	$GLOBALS['tdw_test_other_price_filters'][] = array( $callback, $priority );
	return $callback;
}

/**
 * Remove every filter added by tdw_test_add_other_price_filter().
 */
function tdw_test_remove_other_price_filters() {
	if ( empty( $GLOBALS['tdw_test_other_price_filters'] ) ) {
		return;
	}
	foreach ( $GLOBALS['tdw_test_other_price_filters'] as $filter ) {
		remove_filter( WC_Taxonomy_Discounts_Webdados()->get_price_filter, $filter[0], $filter[1] );
	}
	$GLOBALS['tdw_test_other_price_filters'] = array();
}

/**
 * Where a callback sits among the callbacks on its priority, 0 being first.
 *
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @return int|false
 */
function tdw_test_position( $callback, $priority ) {
	global $wp_filter;
	$hook = WC_Taxonomy_Discounts_Webdados()->get_price_filter;
	if ( ! isset( $wp_filter[ $hook ]->callbacks[ $priority ] ) ) {
		return false;
	}
	return array_search( _wp_filter_build_unique_id( $hook, $callback, $priority ), array_keys( $wp_filter[ $hook ]->callbacks[ $priority ] ), true );
}

/**
 * A fresh copy of the test product, so nothing is carried over in the object between steps.
 *
 * @return WC_Product
 */
function tdw_test_product() {
	$fixture = tdw_test_fixture();
	return wc_get_product( $fixture['product_id'] );
}

/**
 * The shop's cart, holding one test product and nothing else.
 *
 * @return WC_Cart
 */
function tdw_test_cart() {
	if ( ! did_action( 'woocommerce_load_cart_from_session' ) && function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( tdw_test_fixture()['product_id'] );
	return WC()->cart;
}

/**
 * The unit price the cart charges for the test product, before tax handling.
 *
 * Read from the cart item rather than the totals: run from the command line the customer has no
 * address, so WooCommerce takes the base tax out of tax inclusive prices and the totals stop
 * matching what a shopper would pay.
 *
 * @param WC_Cart $cart Cart.
 * @return float|false
 */
function tdw_test_cart_price( $cart ) {
	foreach ( $cart->get_cart() as $item ) {
		if ( (int) $item['data']->get_id() === tdw_test_fixture()['product_id'] ) {
			return (float) $item['data']->get_price( 'edit' );
		}
	}
	return false;
}
