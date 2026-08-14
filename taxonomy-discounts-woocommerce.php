<?php
/**
 * Plugin Name:          Taxonomy/Term and Role-based Discounts for WooCommerce
 * Description:          Automatically apply WooCommerce discounts/pricing rules based on product category, tag, attribute, custom taxonomy, and user role — no coupons needed
 * Version:              8.4.1
 * Author:               Naked Cat Plugins (by Webdados)
 * Author URI:           https://nakedcatplugins.com
 * Text Domain:          taxonomy-discounts-woocommerce
 * Requires at least:    5.8
 * Tested up to:         7.1
 * Requires PHP:         7.2
 * WC requires at least: 7.1
 * WC tested up to:      11.0
 * Requires Plugins:     woocommerce
 * License:              GPLv3
 */

/* Partially WooCommerce CRUD ready - Term metas are still fetched from the database using WP_Query for filtering and performance reasons */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Our own order class and the main classes
 */
function wctd_init() {
	if ( class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.1', '>=' ) ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = get_plugin_data( __FILE__, false, false );
		define( 'WCTD_FREE_PLUGIN_VERSION', $plugin_data['Version'] );
		define( 'WCTD_FREE_PLUGIN_FILE', __FILE__ );
		require_once 'includes/class-wc-taxonomy-discounts-webdados.php';
		require_once 'includes/helpers.php';
		$GLOBALS['WC_Taxonomy_Discounts_Webdados'] = WC_Taxonomy_Discounts_Webdados();
	} else {
		add_action( 'admin_notices', 'wctd_init_no_woocommerce' );
	}
}
add_action( 'setup_theme', 'wctd_init', 1 );

/**
 * Main class
 *
 * @return WC_Taxonomy_Discounts_Webdados
 */
function WC_Taxonomy_Discounts_Webdados() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return WC_Taxonomy_Discounts_Webdados::instance();
}

/**
 * Dependecies warning
 */
function wctd_init_no_woocommerce() {
	?>
	<div id="message" class="error">
		<p>
			<?php echo wp_kses_post( __( '<strong>Taxonomy/Term and Role-based Discounts for WooCommerce</strong> is enabled but not effective. It requires <strong>WooCommerce</strong> in order to work.', 'taxonomy-discounts-woocommerce' ) ); ?>
		</p>
	</div>
	<?php
}

/**
 * On activation, set a transient so we can redirect to the settings page.
 */
function wctd_redirect_on_activation() {
	set_transient( 'wctd_activation_redirect_' . get_current_user_id(), true, 30 );
}
register_activation_hook( __FILE__, 'wctd_redirect_on_activation' );

/**
 * Redirect to the settings page after single (non-bulk) activation.
 */
add_action(
	'admin_init',
	function () {
		// Do not redirect during AJAX requests.
		if ( wp_doing_ajax() ) {
			return;
		}
		$transient_key = 'wctd_activation_redirect_' . get_current_user_id();
		if ( get_transient( $transient_key ) ) {
			delete_transient( $transient_key );
			// Do not redirect on bulk activation.
			if ( ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=wc_taxonomy_discounts_webdados' ) );
				exit;
			}
		}
	}
);

/* HPOS Compatible */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/* If you're reading this you must know what you're doing ;-) Greetings from sunny Portugal! */
