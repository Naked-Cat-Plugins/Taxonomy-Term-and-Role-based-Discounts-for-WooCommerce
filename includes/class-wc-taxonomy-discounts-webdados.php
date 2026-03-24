<?php
/**
 * Main class file for WooCommerce Taxonomy Discounts plugin
 *
 * Handles taxonomy and role-based discount functionality for WooCommerce products
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Our main class
 */
class WC_Taxonomy_Discounts_Webdados {

	private static $instance;

	public $version;
	public $discount_rule_meta_key;
	public $discount_rules_frontend;
	public $discount_rules_backend;
	public $cache_on_sale;
	public $cache_on_get_price;
	public $cache_do_not_apply_discount;
	public $enable_cache;
	public $get_price_filter;
	public $get_price_filter_priority;
	public $enable_time;
	public $wpml_active;
	public $flatsome_active;
	public $pro_addon_link;
	public $discount_types;
	public $debug;
	public $rules_aggregate_variations;

	/**
	 * Initialize the singleton instance
	 *
	 * Creates a new instance of the class if one doesn't exist yet
	 */
	public static function init() {
		if ( self::$instance === null ) {
			self::$instance = new WC_Taxonomy_Discounts_Webdados();
		}
	}

	/**
	 * Get the singleton instance
	 *
	 * @return WC_Taxonomy_Discounts_Webdados The singleton instance of this class
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::init();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * Initializes class properties, loads text domain, and sets up WordPress hooks for admin and frontend functionality
	 */
	public function __construct() {

		$this->version                     = WCTD_FREE_PLUGIN_VERSION;
		$this->discount_rule_meta_key      = 'tdw_discount_rule';
		$this->discount_rules_frontend     = false;
		$this->discount_rules_backend      = false;
		$this->cache_on_sale               = array();
		$this->cache_on_get_price          = array();
		$this->cache_do_not_apply_discount = array();
		$this->enable_cache                = false;
		$this->get_price_filter            = 'woocommerce_product_get_price';
		$this->get_price_filter_priority   = 10;
		$this->enable_time                 = true; // Since 0.9
		$this->wpml_active                 = class_exists( 'SitePress' );
		$this->flatsome_active             = false;
		$this->pro_addon_link              = 'https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/';

		if ( is_admin() ) {

			if ( ! defined( 'DOING_AJAX' ) ) {
				// Add menu item
				add_action( 'admin_menu', array( &$this, 'add_to_admin_menu' ), 99 );
				// Add CSS and JS
				add_action( 'admin_enqueue_scripts', array( &$this, 'admin_css_js' ) );
				// Add our screen to WooCommerce admin screens so we get the tooltips
				add_action( 'woocommerce_screen_ids', array( &$this, 'woocommerce_screen_ids' ) );
				// Add the settings link on the plugins page
				add_filter( 'plugin_action_links_' . plugin_basename( WCTD_FREE_PLUGIN_FILE ), array( &$this, 'add_settings_link' ) );
			}
			// Ajax calls
			add_action( 'wp_ajax_tdw_form_add_choose_taxonomy', array( &$this, 'ajax_form_add_choose_taxonomy' ) );
			add_action( 'wp_ajax_tdw_form_add_submit', array( &$this, 'ajax_form_add_submit' ) );
			add_action( 'wp_ajax_tdw_form_edit_submit', array( &$this, 'ajax_form_edit_submit' ) );
			add_action( 'wp_ajax_tdw_rules_table', array( &$this, 'admin_page_rules_table_ajax' ) );
			add_action( 'wp_ajax_tdw_delete_rule', array( &$this, 'ajax_delete_rule' ) );

		}
		add_action( 'plugins_loaded', array( &$this, 'init_public_filters' ) );
	}

	/**
	 * Get the plugin URL
	 *
	 * @return string The absolute URL to the plugin directory
	 */
	public static function plugin_url() {
		return plugins_url( '/', WCTD_FREE_PLUGIN_FILE );
	}

	/**
	 * Initialize public-facing filters and hooks
	 *
	 * Sets up price filters, sale badge modifications, cart hooks, and other frontend functionality
	 */
	public function init_public_filters() {
		// Debug
		$this->debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		// Filter priority - old constant - Maybe turn into a filter?
		if ( defined( 'WCTD_GET_PRICE_FILTER_PRIO' ) && intval( WCTD_GET_PRICE_FILTER_PRIO ) > 0 ) {
			$this->get_price_filter_priority = intval( WCTD_GET_PRICE_FILTER_PRIO );
		}
		// Allow themes to use the filters
		add_action(
			'after_setup_theme',
			function () {
				// Discount types
				$this->discount_types = apply_filters( 'tdw_discount_types', array( 'percentage', 'x-for-y' ) );
				// Cache
				$this->enable_cache = apply_filters( 'tdw_enable_cache', true );
				if ( $this->debug ) {
					do_action( 'qm/debug', 'WC_Taxonomy_Discounts_Webdados cache enabled: ' . ( $this->enable_cache ? 'yes' : 'no' ) );
				}
			}
		);
		// Flatsome?
		add_action(
			'after_setup_theme',
			function () {
				$this->flatsome_active = defined( 'UXTHEMES_API_URL' );
			}
		);
		// Set the rules that can have aggregate variations
		$this->rules_aggregate_variations = apply_filters( 'tdw_rules_aggregate_variations', array( 'percentage' ) );
		// Maybe the price filters should come here...
		// Price - Product and listings page
		add_filter( $this->get_price_filter, array( &$this, 'on_get_price' ), $this->get_price_filter_priority, 2 );
		add_action( 'woocommerce_before_mini_cart', array( &$this, 'cart_remove_price_filters' ) );
		add_action( 'woocommerce_after_mini_cart', array( &$this, 'cart_add_price_filters' ) );
		// On sale?
		add_filter( 'woocommerce_product_is_on_sale', array( &$this, 'on_get_product_is_on_sale' ), 10, 2 );
		// Add the actions to trigger price adjustments
		add_action( 'woocommerce_cart_loaded_from_session', array( &$this, 'on_cart_loaded_from_session' ), 99, 1 );
		add_action( 'woocommerce_before_calculate_totals', array( &$this, 'cart_remove_price_filters' ), 98, 1 );
		add_action( 'woocommerce_before_calculate_totals', array( &$this, 'on_calculate_totals' ), 99, 1 );
		add_action( 'woocommerce_after_calculate_totals', array( &$this, 'cart_add_price_filters' ), 99 );
		// Hook into cart prices output
		add_filter( 'woocommerce_cart_item_price', array( &$this, 'on_display_cart_item_price_html' ), 99, 2 );
		add_filter( 'woocommerce_cart_item_subtotal', array( &$this, 'on_display_cart_item_subtotal_html' ), 99, 3 );
		// Variation prices html
		add_filter( 'woocommerce_available_variation', array( &$this, 'woocommerce_available_variation' ), 99, 3 );
		add_filter( 'woocommerce_variation_prices', array( &$this, 'woocommerce_variation_prices' ), 99, 2 );
		add_filter( 'woocommerce_get_variation_sale_price', array( &$this, 'woocommerce_get_variation_sale_price' ), 99, 2 ); // UpStore theme, sales badge compatibility
		// Coupon is valid?
		add_filter( 'woocommerce_coupon_is_valid', array( &$this, 'woocommerce_fixed_cart_coupon_is_valid' ), 10, 2 );
		add_filter( 'woocommerce_coupon_get_discount_amount', array( &$this, 'woocommerce_coupon_get_discount_amount' ), 10, 3 );
		// Show percentage discount on the sale badge
		if ( ( defined( 'WCTD_PERC_SALE_BADGE' ) && WCTD_PERC_SALE_BADGE )
			||
			apply_filters( 'tdw_perc_sale_badge', false )
		) {
			// Classic
			add_filter( 'woocommerce_sale_flash', array( &$this, 'woocommerce_sale_flash' ), 10, 3 );
			// Blocks
			add_filter( 'woocommerce_sale_badge_text', array( &$this, 'woocommerce_sale_badge_text' ), 10, 2 );
		}
		// Show discount information on the loop
		$wctd_loop_disc_info_action = '';
		$wctd_loop_disc_info_prio   = '';
			// Old constants - deprecated, to be removed
		if ( defined( 'WCTD_LOOP_DISC_INFO_ACTION' ) && trim( WCTD_LOOP_DISC_INFO_ACTION ) !== '' && defined( 'WCTD_LOOP_DISC_INFO_PRIO' ) && intval( WCTD_LOOP_DISC_INFO_PRIO ) > 0 ) {
			$wctd_loop_disc_info_action = WCTD_LOOP_DISC_INFO_ACTION;
			$wctd_loop_disc_info_prio   = WCTD_LOOP_DISC_INFO_PRIO;

		}
			// New filters
		if ( apply_filters( 'tdw_loop_disc_info_action', false ) !== false && apply_filters( 'tdw_loop_disc_info_prio', false ) !== false ) {
			$wctd_loop_disc_info_action = apply_filters( 'tdw_loop_disc_info_action', false );
			$wctd_loop_disc_info_prio   = apply_filters( 'tdw_loop_disc_info_prio', false );
		}
		if ( ! empty( $wctd_loop_disc_info_action ) && ! empty( $wctd_loop_disc_info_prio ) ) {
			add_action( $wctd_loop_disc_info_action, array( &$this, 'discount_information_loop' ), $wctd_loop_disc_info_prio );
		}
		// Show discount information on the product page
		$wctd_single_disc_info_action = '';
		$wctd_single_disc_info_prio   = '';
			// Old constants - deprecated, to be removed
		if ( defined( 'WCTD_PROD_DISC_INFO_ACTION' ) && trim( WCTD_PROD_DISC_INFO_ACTION ) !== '' && defined( 'WCTD_PROD_DISC_INFO_PRIO' ) && intval( WCTD_PROD_DISC_INFO_PRIO ) > 0 ) {
			$wctd_single_disc_info_action = WCTD_PROD_DISC_INFO_ACTION;
			$wctd_single_disc_info_prio   = WCTD_PROD_DISC_INFO_PRIO;
		}
			// New filters
		if ( apply_filters( 'tdw_single_disc_info_action', false ) !== false && apply_filters( 'tdw_single_disc_info_prio', false ) !== false ) {
			$wctd_single_disc_info_action = apply_filters( 'tdw_single_disc_info_action', false );
			$wctd_single_disc_info_prio   = apply_filters( 'tdw_single_disc_info_prio', false );
		}
		if ( ! empty( $wctd_single_disc_info_action ) && ! empty( $wctd_single_disc_info_prio ) ) {
			add_action( $wctd_single_disc_info_action, array( &$this, 'discount_information_single' ), $wctd_single_disc_info_prio );
		}
		// KuantoKusta plugin integration
		add_filter( 'kuantokusta_product_node_default_current_price', array( &$this, 'kuantokusta_product_node_default_current_price' ), 10, 2 );
		add_filter( 'kuantokusta_product_node_variation_current_price', array( &$this, 'kuantokusta_product_node_variation_current_price' ), 10, 3 );
	}

	/**
	 * Add admin menu item
	 *
	 * Registers a submenu page under WooCommerce Products for managing discount rules
	 */
	public function add_to_admin_menu() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$slug = add_submenu_page(
				'edit.php?post_type=product',
				__( 'Taxonomy/Term and Role based Discounts for WooCommerce', 'taxonomy-discounts-woocommerce' ),
				__( 'Taxonomy Discounts', 'taxonomy-discounts-woocommerce' ),
				'manage_woocommerce',
				'wc_taxonomy_discounts_webdados',
				array( &$this, 'admin_page' )
			);
		}
	}

	/**
	 * Admin page CSS and JS
	 *
	 * @param string $hook The hook used.
	 */
	public function admin_css_js( $hook ) {
		if ( $hook === 'product_page_wc_taxonomy_discounts_webdados' ) { // Before it was 'woocommerce_page_wc_taxonomy_discounts_webdados'
			$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';
			wp_enqueue_style( 'jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $jquery_version . '/themes/smoothness/jquery-ui.css' ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_style( 'tdw_admin_styles', self::plugin_url() . 'admin/styles.css', array( 'jquery-ui-style' ), $this->version );
			wp_enqueue_script( 'tdw_admin_js', self::plugin_url() . 'admin/functions.js', array( 'jquery', 'jquery-ui-datepicker' ), $this->version, true );
			$localization = apply_filters(
				'tdw_admin_js_localization',
				array(
					'string_are_you_sure_delete_rule' => esc_html__( 'Are you sure you want to permanently delete this discount rule?', 'taxonomy-discounts-woocommerce' ),
				)
			);
			wp_localize_script( 'tdw_admin_js', 'tdw_admin_js', $localization );
		}
	}

	/**
	 * Add our screen to WooCommerce admin screens so we get the tooltips
	 *
	 * @param array $screen_ids The WooCommerce screen IDs.
	 * @return array
	 */
	public function woocommerce_screen_ids( $screen_ids ) {
		$screen_ids[] = 'product_page_wc_taxonomy_discounts_webdados';
		return $screen_ids;
	}

	/**
	 * Add settings link on the plugins page
	 *
	 * @param array $links The existing plugin action links.
	 * @return array       Modified array of plugin action links with our settings link added.
	 */
	public function add_settings_link( $links ) {
		$action_links = array(
			'<a href="' . admin_url( 'edit.php?post_type=product&page=wc_taxonomy_discounts_webdados' ) . '">' . esc_html__( 'Settings and discount configuration', 'taxonomy-discounts-woocommerce' ) . '</a>',
		);
		return array_merge( $links, $action_links );
	}

	/**
	 * Remove WPML term filters temporarily
	 *
	 * Removes WPML filters to allow retrieving terms from all languages
	 */
	public function remove_wpml_terms_filters() {
		if ( $this->wpml_active ) {
			global $sitepress;
			remove_filter( 'get_terms_args', array( $sitepress, 'get_terms_args_filter' ) );
			remove_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ) );
			remove_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ) );
		}
	}

	/**
	 * Restore WPML term filters
	 *
	 * Re-adds WPML filters that were temporarily removed
	 */
	public function restore_wpml_terms_filters() {
		if ( $this->wpml_active ) {
			global $sitepress;
			add_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ), 10, 3 );
			add_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ) );
			add_filter( 'get_terms_args', array( $sitepress, 'get_terms_args_filter' ), 10, 2 );
		}
	}

	/**
	 * Get taxonomy terms with WPML compatibility
	 *
	 * Retrieves terms from a taxonomy, handling WPML multi-language scenarios if active
	 *
	 * @param string $taxonomy The taxonomy to get the terms from.
	 * @param array  $args     Arguments to pass to get_terms().
	 * @return array           Array of term objects.
	 */
	public function get_terms( $taxonomy, $args ) {
		$args['taxonomy'] = $taxonomy;
		if ( $this->wpml_active && wp_doing_ajax() ) {
			$terms     = array();
			$languages = icl_get_languages( 'skip_missing=0&orderby=code' );
			foreach ( $languages as $language ) {
				do_action( 'wpml_switch_language', $language['code'] );
				$terms = array_merge( $terms, (array) get_terms( $args ) );
			}
			do_action( 'wpml_switch_language', ICL_LANGUAGE_CODE );
			// Avoid duplicates for non-translated terms - array_unique is only for strings
			$temp = array();
			foreach ( $terms as $term ) {
				if ( ! in_array( $term, $temp, true ) ) {
					$temp[] = $term;
				}
			}
			$terms = $temp;
		} else {
			self::remove_wpml_terms_filters();
			$terms = (array) get_terms( $args );
			self::restore_wpml_terms_filters();
		}
		return $terms;
	}

	/**
	 * Get a single term with WPML compatibility
	 *
	 * Retrieves a specific term, handling WPML language context if active
	 *
	 * @param int    $term_id  The term ID.
	 * @param string $taxonomy The taxonomy name.
	 * @return WP_Term|WP_Error|null Term object, WP_Error on failure, or null if not found.
	 */
	public function get_term( $term_id, $taxonomy ) {
		if ( $this->wpml_active && wp_doing_ajax() ) {
			$language_code = apply_filters(
				'wpml_element_language_code',
				null,
				array(
					'element_id'   => (int) $term_id,
					'element_type' => $taxonomy,
				)
			);
			if ( ! empty( $language_code ) ) {
				do_action( 'wpml_switch_language', $language_code );
			} else {
				do_action( 'wpml_switch_language', ICL_LANGUAGE_CODE );
			}
			self::remove_wpml_terms_filters();
			$term = get_term( $term_id, $taxonomy );
			self::restore_wpml_terms_filters();
			do_action( 'wpml_switch_language', ICL_LANGUAGE_CODE );
		} else {
			self::remove_wpml_terms_filters();
			$term = get_term( $term_id, $taxonomy );
			self::restore_wpml_terms_filters();
		}
		return $term;
	}

	/**
	 * Display or retrieve HTML dropdown list of categories (WPML compatible)
	 *
	 * Wrapper around wp_dropdown_categories() with WPML compatibility
	 *
	 * @param array $args Arguments for wp_dropdown_categories().
	 * @return string|void HTML dropdown markup if 'echo' is false, otherwise outputs directly.
	 */
	public function wp_dropdown_categories( $args ) {
		$return       = ! ( isset( $args['echo'] ) ? $args['echo'] : true );
		$args['echo'] = false;
		// We only show the current language terms, so we don't do self::remove_wpml_terms_filters();
		$dropdow = wp_dropdown_categories( $args );
		if ( $return ) {
			return $dropdow;
		} else {
			// Not escaped because wp_dropdown_categories does not need it (and it fails with wp_kses_post)
			echo $dropdow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Display HTML dropdown list of user roles
	 *
	 * Outputs a select element with all available user roles plus special options for all users and logged-in users
	 *
	 * @param string $action   Form action context ('add' or 'edit').
	 * @param string $selected The currently selected role value.
	 */
	public function wp_dropdown_roles( $action, $selected ) {
		$options   = array(
			'_all_users_' => '- ' . esc_html__( 'all users', 'taxonomy-discounts-woocommerce' ) . ' -',
			'_logged_in_' => '- ' . esc_html__( 'logged-in users', 'taxonomy-discounts-woocommerce' ) . ' -',
		);
		$all_roles = wp_roles()->roles;
		foreach ( $all_roles as $role => $details ) {
			$name             = translate_user_role( $details['name'] );
			$options[ $role ] = $name;
		}
		$options = apply_filters( 'tdw_admin_available_user_roles', $options );
		?>
		<select name="tdw-form-<?php echo esc_attr( $action ); ?>-role" id="tdw-form-<?php echo esc_attr( $action ); ?>-role">
			<?php
			foreach ( $options as $key => $temp ) {
				?>
				<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $selected, $key ); ?>><?php echo esc_html( $temp ); ?></option>
				<?php
			}
			?>
		</select>
		<?php
	}

	/**
	 * Get human-readable name for a discount rule type
	 *
	 * @param string $type       The rule type identifier (e.g., 'percentage', 'x-for-y').
	 * @param bool   $strip_tags Whether to strip HTML tags from the name (default: false).
	 * @return string      The translated human-readable name for the rule type.
	 */
	public function get_rule_type_name( $type, $strip_tags = false ) {
		switch ( $type ) {
			case 'percentage':
				$name = esc_html__( 'Percentage', 'taxonomy-discounts-woocommerce' );
				break;
			case 'x-for-y':
				$name = esc_html__( 'Buy x get y free', 'taxonomy-discounts-woocommerce' );
				break;
			default:
				$name = apply_filters( 'tdw_non_default_rule_type_name', 'error', $type );
				break;
		}
		if ( $strip_tags ) {
			$name = wp_strip_all_tags( $name );
		}
		return $name;
	}

	/**
	 * Get all discount rules from the database
	 *
	 * Retrieves and caches discount rules, with optional filtering for frontend display
	 *
	 * @param bool $frontend Whether to retrieve only active and valid rules for frontend display.
	 * @return array         Multi-dimensional array of discount rules organized by priority and term ID.
	 */
	public function get_discount_rules( $frontend = false ) {
		if ( $this->debug ) {
			do_action( 'qm/start', 'WC_Taxonomy_Discounts_Webdados::get_discount_rules' );
		}
		$this_discount_rules = $frontend ? $this->discount_rules_frontend : $this->discount_rules_backend;
		if ( ! $this_discount_rules ) { // Not on this page load cache?
			// Rules database cache from PRO add-on
			$discount_rules = apply_filters( 'tdw_discount_rules_database_cache', false );
			if ( $frontend && ! empty( $discount_rules ) ) {
				$this->discount_rules_frontend = $discount_rules;
				if ( $this->debug ) {
					do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::get_discount_rules' );
				}
				return $discount_rules;
			}
			// Let's get them old fashion way
			$discount_rules   = array();
			$taxonomy_objects = self::get_product_taxonomies();
			if ( count( $taxonomy_objects ) > 0 ) {
				global $wpdb;
				$taxonomies = array();
				foreach ( $taxonomy_objects as $tax => $taxonomy ) {
					$taxonomies[] = $tax;
				}
				$args  = array(
					'hide_empty' => false,
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key' => $this->discount_rule_meta_key,
						),
					),
				);
				$terms = self::get_terms( $taxonomies, $args );
				if ( count( $terms ) > 0 ) {
					$count_rules = 0;
					foreach ( $terms as $term ) {
						// Maybe we should not be using SQL? It's faster and we can get them all
						$term_meta = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare(
								"SELECT * FROM $wpdb->termmeta WHERE term_id = %d AND meta_key = %s",
								$term->term_id,
								$this->discount_rule_meta_key
							)
						);
						foreach ( $term_meta as $term_meta_1 ) {
							$term_meta_value           = maybe_unserialize( $term_meta_1->meta_value );
							$term_meta_value['active'] = isset( $term_meta_value['active'] ) ? $term_meta_value['active'] : false;
							// In the frontend do not load inactive rules for performance reasons
							if ( $frontend && ! $term_meta_value['active'] ) {
								continue;
							}
							$term_meta_value['disable_coupon'] = isset( $term_meta_value['disable_coupon'] ) ? $term_meta_value['disable_coupon'] : false;
							$term_meta_value['term_id']        = intval( $term->term_id );
							$term_meta_value['meta_id']        = intval( $term_meta_1->meta_id );
							if ( $term_meta_value['type'] === 'percentage' && ! isset( $term_meta_value['min-qtt'] ) ) {
								$term_meta_value['min-qtt'] = 0; // Since 0.3
							}
							if ( $term_meta_value['type'] === 'percentage' && ! isset( $term_meta_value['aggr-var'] ) ) {
								$term_meta_value['aggr-var'] = 0; // Since 0.6
							}
							if ( isset( $term_meta_value['priority'] ) ) {
								if ( ! isset( $discount_rules[ $term_meta_value['priority'] ] ) ) {
									$discount_rules[ $term_meta_value['priority'] ] = array();
								}
								if ( ! isset( $discount_rules[ $term_meta_value['priority'] ][ $term->term_id ] ) ) {
									$discount_rules[ $term_meta_value['priority'] ][ $term->term_id ] = array();
								}
								// In the frontend do not load invalid/expired rules
								if ( $frontend ) {
									if ( ! ( WC_Taxonomy_Discounts_Webdados()->valid_rule_user_role( $term_meta_value ) && WC_Taxonomy_Discounts_Webdados()->valid_rule_date( $term_meta_value ) ) ) {
										continue;
									}
								}
								// Add to current discount rules for performance reasons
								++$count_rules;
								$discount_rules[ $term_meta_value['priority'] ][ $term->term_id ][] = $term_meta_value;
							}
						}
					}
				}
			}
			// Allow Pro add-on or other plugins to modify the rules
			$discount_rules = apply_filters( 'tdw_get_discount_rules', $discount_rules );
			// Allow discounts that will go to zero?
			foreach ( $discount_rules as $priority => $rules_by_term ) {
				foreach ( $rules_by_term as $term_id => $rules ) {
					foreach ( $rules as $key => $rule ) {
						$discount_rules[ $priority ][ $term_id ][ $key ]['allows-discount-to-zero'] = $this->rule_allows_discount_to_zero( $rule );
					}
				}
			}
			// Sort them by priority
			ksort( $discount_rules );
			if ( $frontend ) {
				$this->discount_rules_frontend = $discount_rules;
			} else {
				$this->discount_rules_backend = $discount_rules;
			}
			// Set on this page load cache
			if ( $this->debug ) {
				do_action( 'qm/lap', 'WC_Taxonomy_Discounts_Webdados::get_discount_rules' );
			}
		}
		if ( $this->debug ) {
			do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::get_discount_rules' );
		}
		return $frontend ? $this->discount_rules_frontend : $this->discount_rules_backend;
	}

	/**
	 * Check if a discount rule allows the price to be reduced to zero
	 *
	 * Determines whether a given discount rule can result in a final price of zero, which may affect coupon applicability and other logic
	 *
	 * @param array $rule The discount rule to check.
	 * @return bool       True if the rule allows discounts to reduce price to zero, false otherwise.
	 */
	private function rule_allows_discount_to_zero( $rule ) {
		// Free plugin rules - None so far, but maybe in the future we will add some that allow it
		$allow_to_zero = false;
		// Allow PRO add-on or other plugins to set if a rule allows discount to zero or not
		$allow_to_zero = apply_filters( 'tdw_rule_allows_discount_to_zero', $allow_to_zero, $rule );
		// Return
		return $allow_to_zero;
	}

	/**
	 * Check if a discount rule applies to the current user's role
	 *
	 * Validates whether a rule should be applied based on the user's login status and role
	 *
	 * @param array $rule The discount rule to validate.
	 * @return bool       True if the rule applies to the current user, false otherwise.
	 */
	public function valid_rule_user_role( $rule ) {
		$user_role = isset( $rule['user_role'] ) && trim( $rule['user_role'] ) !== '' ? trim( $rule['user_role'] ) : '';
		$return    = false;
		switch ( $user_role ) {
			case '': // Until 3.1.2
			case '_all_users_':
				$return = true;
				break;
			case '_logged_in_':
				$return = is_user_logged_in();
				break;
			default:
				if ( is_user_logged_in() ) {
					$user   = wp_get_current_user();
					$return = in_array( $user_role, (array) $user->roles, true );
				} else {
					$return = false;
				}
				break;
		}
		return apply_filters( 'tdw_valid_rule_user_role', $return, $rule );
	}

	/**
	 * Check if a discount rule is valid based on current date and time
	 *
	 * Validates whether a rule is active and falls within its specified date/time range
	 *
	 * @param array $rule The discount rule to validate.
	 * @return bool       True if the rule is currently valid, false otherwise.
	 */
	public function valid_rule_date( $rule ) {
		if ( $rule['active'] ) {
			$execute_rules = true;
			$now           = strtotime( 'now' );
			$from          = false;
			if ( isset( $rule['from'] ) && trim( $rule['from'] ) !== '' ) {
				if ( $this->enable_time ) {
					if ( strlen( trim( $rule['from'] ) ) > 10 ) {
						$from = trim( $rule['from'] );
					} else {
						$from = substr( trim( $rule['from'] ), 0, 10 ) . ' 00:00:00';
					}
				} else {
					$from = substr( trim( $rule['from'] ), 0, 10 ) . ' 00:00:00';
				}
				$from = strtotime( $from );
			}
			$to = false;
			if ( isset( $rule['to'] ) && trim( $rule['to'] ) !== '' ) {
				if ( $this->enable_time ) {
					if ( strlen( trim( $rule['to'] ) ) > 10 ) {
						$to = trim( $rule['to'] );
					} else {
						$to = substr( trim( $rule['to'] ), 0, 10 ) . ' 23:59:59';
					}
				} else {
					$to = substr( trim( $rule['to'] ), 0, 10 ) . ' 23:59:59';
				}
				$to = strtotime( $to );
			}
			if ( $from && $to && ! ( $now >= $from && $now <= $to ) ) {
				$execute_rules = false;
			} elseif ( $from && ! $to && ! ( $now >= $from ) ) {
				$execute_rules = false;
			} elseif ( $to && ! $from && ! ( $now <= $to ) ) {
				$execute_rules = false;
			}
		} else {
			$execute_rules = false;
		}
		return $execute_rules;
	}

	/**
	 * Get the applicable discount rule for a specific product
	 *
	 * Finds and returns the first matching discount rule for a product based on taxonomy terms, user role, and date validity
	 *
	 * @param WC_Product|int $product Product object or product ID.
	 * @return array|false            The applicable discount rule array, or false if no rule applies.
	 */
	public function get_product_applied_rule( $product ) {
		$found          = false;
		$discount_rules = self::get_discount_rules( true );
		if ( count( $discount_rules ) ) {
			if ( is_int( $product ) ) {
				$product = wc_get_product( $product );
			}
			if ( $product ) {
				$product_id = $product->get_id();
				foreach ( $discount_rules as $priority => $terms ) {
					foreach ( $terms as $term_id => $rules ) {
						foreach ( $rules as $rule_key => $rule ) {
							if ( WC_Taxonomy_Discounts_Webdados()->valid_rule_user_role( $rule ) && WC_Taxonomy_Discounts_Webdados()->valid_rule_date( $rule ) && isset( $rule['type'] ) ) {
								if (
									has_term( $term_id, $rule['taxonomy'], $product_id )
									||
									// Allow PRO add-on or other plugins to extend the has_term check and return valid even if the product does not have the term
									apply_filters( 'tdw_has_term_or_valid', false, has_term( $term_id, $rule['taxonomy'], $product_id ), $term_id, $rule['taxonomy'], $product_id )
								) {
									return apply_filters( 'tdw_get_product_applied_rule', $rule, $product );
								}
							}
						}
					}
				}
			}
		}
		return false;
	}

	/**
	 * Calculate discounted price for a product
	 *
	 * Applies taxonomy-based discount rules to product prices on shop pages, archives, and product pages
	 *
	 * @param float      $base_price        The original product price.
	 * @param WC_Product $_product          The product object.
	 * @param bool       $force_calculation Whether to force calculation bypassing cache.
	 * @param int        $qty               Product quantity for quantity-based discounts.
	 * @return float                        The discounted price or original price if no discount applies.
	 */
	public function on_get_price( $base_price, $_product, $force_calculation = false, $qty = 1 ) {
		if ( $this->debug ) {
			do_action( 'qm/start', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
		}
		// if ( ( ! $force_calculation ) && $this->enable_cache && isset( $this->cache_on_get_price[$_product->get_id()] ) ) { // Causes double run on product archives??
		if ( $this->enable_cache && isset( $this->cache_on_get_price[ $_product->get_id() ] ) ) {
			if ( $this->debug ) {
				do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
			}
			// Issue #7 (PRO) happens here
			return $this->cache_on_get_price[ $_product->get_id() ];
		}
		if ( is_numeric( $base_price ) ) {
			$composite_ajax = did_action( 'wp_ajax_woocommerce_show_composited_product' ) || did_action( 'wp_ajax_nopriv_woocommerce_show_composited_product' ) || did_action( 'wc_ajax_woocommerce_show_composited_product' );

			/*
			// At the moment we're only considering the shortcode and "products" loop, but maybe we could consider them all?
			global $woocommerce_loop;
			$on_woocommerce_loop = $woocommerce_loop && $woocommerce_loop['name'] !== '';
			*/
			if (
				is_product() // Product page
				||
				is_shop() // Shop page
				||
				is_product_category() // Category archive
				||
				is_product_tag() // Tag archive
				||
				is_product_taxonomy() // Custom taxonomy archive
				||
				$force_calculation // Forced
				||
				$composite_ajax // Ajax composite products
				||
				( isset( $GLOBALS['woocommerce_loop'] ) && isset( $GLOBALS['woocommerce_loop']['name'] ) && $GLOBALS['woocommerce_loop']['name'] === 'product' && isset( $GLOBALS['woocommerce_loop']['is_shortcode'] ) && $GLOBALS['woocommerce_loop']['is_shortcode'] === true ) // WooCommerce products shortcode
				||
				( isset( $GLOBALS['woocommerce_loop'] ) && isset( $GLOBALS['woocommerce_loop']['name'] ) && $GLOBALS['woocommerce_loop']['name'] === 'products' ) // General WooCommerce loop (like the homepage)
				||
				apply_filters( 'tdw_custom_product_loop', false )
				// ||
				// Not working if permalinks are enabled for this feed, for example https://wordpress.local/woocommerce_gpf/309edb4b77b4ed20/
				// Moved to PRO add-on
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				// isset( $_GET['woocommerce_gpf'] ) // WooCommerce Google Product Feed - https://woocommerce.com/products/google-product-feed/
			) {
				if ( $this->debug ) {
					do_action( 'qm/lap', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
				}
				// Fix checkout ajax - https://wordpress.org/support/topic/no-passo-de-finalizar-encomenda-os-valores-estao-errados/
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( is_ajax() && isset( $_GET['wc-ajax'] ) && 'update_order_review' === trim( sanitize_text_field( wp_unslash( $_GET['wc-ajax'] ) ) ) ) {
					$this->cache_on_get_price[ $_product->get_id() ] = $base_price;
					if ( $this->debug ) {
						do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
					}
					return $base_price;
				}
				// Go ahead
				$discount_rules = self::get_discount_rules( true );
				if ( $this->debug ) {
					do_action( 'qm/lap', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
				}
				if ( count( $discount_rules ) ) {

					if ( $_product->get_parent_id() ) {
						$product_id      = $_product->get_id();
						$product_id_base = $_product->get_parent_id();
					} else {
						$product_id      = $_product->get_id();
						$product_id_base = $product_id;
					}

					$discount_price = false;

					if ( $this->debug ) {
						do_action( 'qm/lap', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
					}
					$rule = self::get_product_applied_rule( $product_id_base );
					if ( ! empty( $rule ) ) {
						if ( $this->debug ) {
							do_action( 'qm/lap', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
						}
						switch ( $rule['type'] ) {
							case 'percentage':
								if ( isset( $rule['value'] ) && is_numeric( $rule['value'] ) && $rule['value'] > 0 ) {
									if ( floatval( $rule['min-qtt'] ) === (float) 0 || floatval( $rule['min-qtt'] ) === (float) 1 || $qty >= floatval( $rule['min-qtt'] ) ) {
										$discount_price              = $base_price - ( $base_price * ( floatval( $rule['value'] ) / 100 ) );
										$discount_price_over_regular = (float) $_product->get_regular_price() - ( (float) $_product->get_regular_price() * ( floatval( $rule['value'] ) / 100 ) );
										if ( apply_filters( 'tdw_recheck_get_product_applied_rule', true, $rule, $product_id, $product_id_base, $base_price, $discount_price, $_product->get_regular_price(), $discount_price_over_regular ) ) {
											$discount_price                                  = apply_filters( 'tdw_on_get_price_discount_price', $discount_price, $discount_price_over_regular, $_product, $rule );
											$this->cache_on_get_price[ $_product->get_id() ] = $discount_price;
											if ( $this->debug ) {
												do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
											}
											if ( $discount_price > 0 || ( isset( $rule['allows-discount-to-zero'] ) && $rule['allows-discount-to-zero'] ) ) {
												return $discount_price;
											}
										}
									}
								}
								break;
							case 'x-for-y':
								// This is a quantity based rule. No way to get the price at this time. Only in cart... - Or can we? BETA
								if ( $qty >= floatval( $rule['x'] ) ) {
									$multiplier                  = floor( $qty / floatval( $rule['x'] ) );
									$qtt_discounted              = apply_filters( 'tdw_x_for_y_qtt_discounted', floatval( $rule['y'] ) * $multiplier, $rule );
									$discount_price              = ( ( $base_price * $qty ) - ( $base_price * $qtt_discounted ) ) / $qty;
									$discount_price_over_regular = ( ( (float) $_product->get_regular_price() * $qty ) - ( (float) $_product->get_regular_price() * $qtt_discounted ) ) / $qty;
									if ( apply_filters( 'tdw_recheck_get_product_applied_rule', true, $rule, $product_id, $product_id_base, $base_price, $discount_price, $_product->get_regular_price(), $discount_price_over_regular ) ) {
										$discount_price                                  = apply_filters( 'tdw_on_get_price_discount_price', $discount_price, $discount_price_over_regular, $_product, $rule );
										$this->cache_on_get_price[ $_product->get_id() ] = $discount_price;
										if ( $this->debug ) {
											do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
										}
										if ( $discount_price > 0 || $rule['allows-discount-to-zero'] ) {
											return $discount_price;
										}
									}
								}
								break;
							default:
								// The pro should return false or an array with discount_price and discount_price_over_regular
								$non_default_discount_price = apply_filters( 'tdw_on_get_price_non_default_rule_discount_price', false, $rule, $base_price, $_product, $force_calculation, $qty );
								if ( $non_default_discount_price !== false && is_array( $non_default_discount_price ) && isset( $non_default_discount_price['discount_price'] ) && isset( $non_default_discount_price['discount_price_over_regular'] ) && is_numeric( $non_default_discount_price['discount_price'] ) && is_numeric( $non_default_discount_price['discount_price_over_regular'] ) ) {
									$discount_price              = $non_default_discount_price['discount_price'];
									$discount_price_over_regular = $non_default_discount_price['discount_price_over_regular'];
									if ( apply_filters( 'tdw_recheck_get_product_applied_rule', true, $rule, $product_id, $product_id_base, $base_price, $discount_price, $_product->get_regular_price(), $discount_price_over_regular ) ) {
										$discount_price                                  = apply_filters( 'tdw_on_get_price_discount_price', $discount_price, $discount_price_over_regular, $_product, $rule );
										$this->cache_on_get_price[ $_product->get_id() ] = $discount_price;
										if ( $this->debug ) {
											do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
										}
										if ( $discount_price > 0 || $rule['allows-discount-to-zero'] ) {
											return $discount_price;
										}
									}
								}
								break;
						}
					}
					// We're caching here because we know that we have run the rules and they do not apply, so we won't need to run them again for this product on this page load
					$this->cache_on_get_price[ $_product->get_id() ] = $base_price;
					if ( $this->debug ) {
						do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
					}
					return $base_price;
				} else {
					// No rules - We should not cache if we're not touching the price
					// $this->cache_on_get_price[ $_product->get_id() ] = $base_price;
					if ( $this->debug ) {
						do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
					}
					return $base_price;
				}
			} else {
				// Conditions do not apply - Issue #7 (PRO) happens here - We should not cache if we're not touching the price
				// $this->cache_on_get_price[ $_product->get_id() ] = $base_price;
				if ( $this->debug ) {
					do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
				}
				return $base_price;
			}
		} else {
			// Not numberic - We should not cache if we're not touching the price
			// $this->cache_on_get_price[ $_product->get_id() ] = $base_price;
			if ( $this->debug ) {
				do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_price - ' . $_product->get_id() . ' - ' . ( $force_calculation ? 'forced' : '' ) );
			}
			return $base_price;
		}
	}

	/**
	 * KuantoKusta plugin integration for single products
	 *
	 * Applies discounts for KuantoKusta feed generation. Cache is disabled due to VAT handling issues
	 *
	 * @param float      $price   The regular product price.
	 * @param WC_Product $product The product object.
	 * @return float              The discounted price with VAT included.
	 */
	public function kuantokusta_product_node_default_current_price( $price, $product ) {
		$this->enable_cache = false;
		return self::on_get_price( $price, $product, true );
	}

	/**
	 * KuantoKusta plugin integration for product variations
	 *
	 * Applies discounts to variations for KuantoKusta feed generation. Cache is disabled due to VAT handling issues
	 *
	 * @param float                $price     The regular variation price.
	 * @param WC_Product           $product   The parent product object.
	 * @param WC_Product_Variation $variation The product variation object.
	 * @return float                          The discounted variation price with VAT included.
	 */
	public function kuantokusta_product_node_variation_current_price( $price, $product, $variation ) {
		$this->enable_cache = false;
		return self::on_get_price( $price, $variation, true );
	}

	/**
	 * Remove price filters before cart calculations
	 *
	 * Temporarily removes price filters to prevent duplicate discount application during cart operations
	 *
	 * @param WC_Cart $cart The cart object.
	 */
	public function cart_remove_price_filters( $cart ) {
		if ( is_object( $cart ) ) {
			if ( ! $cart->is_empty() ) {
				remove_filter( $this->get_price_filter, array( &$this, 'on_get_price' ), $this->get_price_filter_priority, 2 );
			}
		}
	}

	/**
	 * Re-add price filters after cart calculations
	 *
	 * Restores price filters that were temporarily removed during cart operations
	 */
	public function cart_add_price_filters() {
		add_filter( $this->get_price_filter, array( &$this, 'on_get_price' ), $this->get_price_filter_priority, 2 );
	}

	/**
	 * Handle cart prices when loading from session
	 *
	 * Resets base prices and recalculates discounts when cart is loaded from session, accounting for currency changes
	 *
	 * @param WC_Cart $cart The cart object loaded from session.
	 */
	public function on_cart_loaded_from_session( $cart ) {
		// We need to account for possible currency changes - Maybe a filter to convert the whole 'taxonomy_discounts' array to be used by the PRO plugin
		if ( count( $cart->cart_contents ) > 0 ) {
			foreach ( $cart->cart_contents as $cart_item_key => $values ) {
				if ( isset( $cart->cart_contents[ $cart_item_key ]['taxonomy_discounts'] ) ) {
					$base_price = WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts']['base_price'];
					WC()->cart->cart_contents[ $cart_item_key ]['data']->set_price( $base_price );
					unset( $cart->cart_contents[ $cart_item_key ]['taxonomy_discounts'] );
				}
			}
		}
		self::on_calculate_totals( $cart );
	}

	/**
	 * Apply discount rules to cart items during total calculation
	 *
	 * Calculates and applies discounts to all cart items based on applicable taxonomy rules, quantities, and variations
	 *
	 * @param WC_Cart $cart The cart object being calculated.
	 */
	public function on_calculate_totals( $cart ) {
		if ( count( $cart->cart_contents ) > 0 ) {
			$discount_rules            = self::get_discount_rules( true );
			$variations                = array();
			$cart_item_keys_variations = array();
			if ( count( $discount_rules ) ) {
				foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
					// Shop as Client Cart Price Changer compatibility - Set custom price, skip our calculations
					if ( isset( $cart_item['sac_custom_price_to_set'] ) ) {
						WC()->cart->cart_contents[ $cart_item_key ]['data']->set_price( $cart_item['sac_custom_price_to_set'] );
						continue;
					}
					// Go on
					$applied_rule = false;
					$_product     = $cart->cart_contents[ $cart_item_key ]['data'];

					$is_variation = false;
					if ( $_product->get_parent_id() ) {
						$product_id                  = $_product->get_id();
						$product_id_base             = $_product->get_parent_id();
						$product                     = new WC_Product_Variation( $product_id ); // Why is this needed?
						$is_variation                = true;
						$cart_item_keys_variations[] = $cart_item_key;
					} else {
						$product_id      = $_product->get_id();
						$product_id_base = $product_id;
						$product         = new WC_Product( $product_id ); // Why is this needed?
					}

					$base_price             = floatval(
						isset( WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts'] )
						?
						WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts']['base_price']
						:
						WC()->cart->cart_contents[ $cart_item_key ]['data']->get_price() // Keep compatibility with other plugins that might mess with the cart value
					);
					$display_price          = floatval(
						isset( WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts'] )
						?
						WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts']['display_price']
						: (
							get_option( 'woocommerce_tax_display_cart' ) === 'excl'
							?
							wc_get_price_excluding_tax( $_product )
							:
							wc_get_price_including_tax( $_product )
						)
					);
					$discount_price         = $base_price;
					$discount_display_price = $display_price;
					// We cannot use the helper here - This is causing the filters from the PRO plugin (like "Exclude on sale") to fail
					// Anyway, if we run the function, we'll add the product to the cache_do_not_apply_discount array
					$rule = self::get_product_applied_rule( $product_id_base );
					// Probably because we need to keep going on the loop (why?)
					foreach ( $discount_rules as $priority => $terms ) {
						foreach ( $terms as $term_id => $rules ) {
							foreach ( $rules as $rule_key => $rule ) {
								if (
									self::valid_rule_user_role( $rule )
									&&
									self::valid_rule_date( $rule )
									&&
									isset( $rule['type'] )
									&&
									has_term( $term_id, $rule['taxonomy'], $cart_item['product_id'] )
									&&
									! in_array( (int) $cart_item['product_id'], $this->cache_do_not_apply_discount, true ) // Fix on sale removal
								) {
									switch ( $rule['type'] ) {
										case 'percentage':
											if ( $is_variation && isset( $rule['aggr-var'] ) && $rule['aggr-var'] && isset( $rule['value'] ) && is_numeric( $rule['value'] ) && floatval( $rule['value'] ) > 0 ) {
												// Aggregate variations
												if ( isset( $variations[ $term_id ][ $rule_key ][ $product_id_base ] ) ) {
													$variations[ $term_id ][ $rule_key ][ $product_id_base ]['quantity'] += $cart_item['quantity'];
												} else {
													$variations[ $term_id ][ $rule_key ][ $product_id_base ] = array(
														'quantity' => $cart_item['quantity'],
													);
												}
											} elseif ( isset( $rule['value'] ) && is_numeric( $rule['value'] ) && floatval( $rule['value'] ) > 0 && $cart_item['quantity'] >= floatval( $rule['min-qtt'] ) ) {
												$discount_price              = $base_price - ( $base_price * ( floatval( $rule['value'] ) / 100 ) );
												$discount_price_over_regular = (float) $_product->get_regular_price() - ( (float) $_product->get_regular_price() * ( floatval( $rule['value'] ) / 100 ) );
												$filtered_discount_price     = apply_filters( 'tdw_on_get_price_discount_price', $discount_price, $discount_price_over_regular, $_product, $rule );
												// Pro is setting for the discount to be on top or regular and not sale?
												if ( floatval( $filtered_discount_price ) !== floatval( $discount_price ) && floatval( $filtered_discount_price ) === floatval( $discount_price_over_regular ) ) { // We need to check these != and == for strict comparison - Changed on 2026-03-21
													$discount_price = $filtered_discount_price;
													$base_price     = $_product->get_regular_price();
													$display_price  = $base_price;
												}
												$discount_display_price = $display_price - ( $display_price * ( floatval( $rule['value'] ) / 100 ) );
											}
											break;
										case 'x-for-y':
											if ( isset( $rule['x'] ) && is_numeric( $rule['x'] ) && floatval( $rule['x'] ) > 0 && isset( $rule['y'] ) && is_numeric( $rule['y'] ) && floatval( $rule['y'] ) > 0 && floatval( $rule['x'] ) > floatval( $rule['y'] ) ) {
												if ( $cart_item['quantity'] >= floatval( $rule['x'] ) ) {
													$multiplier                  = floor( $cart_item['quantity'] / floatval( $rule['x'] ) );
													$qtt_discounted              = apply_filters( 'tdw_x_for_y_qtt_discounted', floatval( $rule['y'] ) * $multiplier, $rule );
													$discount_price              = ( ( $base_price * $cart_item['quantity'] ) - ( $base_price * $qtt_discounted ) ) / $cart_item['quantity'];
													$discount_price_over_regular = ( ( (float) $_product->get_regular_price() * $cart_item['quantity'] ) - ( (float) $_product->get_regular_price() * $qtt_discounted ) ) / $cart_item['quantity'];
													$filtered_discount_price     = apply_filters( 'tdw_on_get_price_discount_price', $discount_price, $discount_price_over_regular, $_product, $rule );
													// Pro is setting for the discount to be on top or regular and not sale?
													if ( floatval( $filtered_discount_price ) !== floatval( $discount_price ) && floatval( $filtered_discount_price ) === floatval( $discount_price_over_regular ) ) { // We need to check these != and == for strict comparison - Changed on 2026-03-21
														$discount_price = $filtered_discount_price;
														$base_price     = $_product->get_regular_price();
														$display_price  = $base_price;
													}
													$discount_display_price = ( ( $display_price * $cart_item['quantity'] ) - ( $display_price * $qtt_discounted ) ) / $cart_item['quantity'];
												}
											}
											break;
										default:
											// Allow PRO to set the applied rule (and missing $discount_price, $discount_display_price and probably others)
											$applied_rule = apply_filters( 'tdw_on_calculate_totals_non_default_rule_applied_rule', false, $rule );
											if ( $applied_rule ) {
												/**
												 * The pro should return false or an array with:
												 * - discount_price
												 * - discount_price_over_regular
												 * - filtered_discount_price
												 * - discount_display_price
												 * - base_price
												 * - variations
												 * Or false if the rule is not to be applied
												 */
												$non_default_discount_price = apply_filters(
													'tdw_on_calculate_totals_non_default_rule_discount_price',
													false,
													$cart_item,
													$_product,
													$is_variation,
													$rule,
													$rule_key,
													$base_price,
													$display_price,
													$discount_price,
													$discount_display_price,
													$term_id,
													$variations, // Why are we getting empty variations again, even if we set them on a previous passage?
													$product_id_base
												);
												if (
													$non_default_discount_price !== false
													&&
													is_array( $non_default_discount_price )
													&&
													isset( $non_default_discount_price['discount_price'] )
													&&
													isset( $non_default_discount_price['discount_price_over_regular'] )
													&&
													isset( $non_default_discount_price['discount_display_price'] )
												) {
													$discount_price              = $non_default_discount_price['discount_price'];
													$discount_price_over_regular = $non_default_discount_price['discount_price_over_regular'];
													$filtered_discount_price     = $non_default_discount_price['filtered_discount_price'];
													$discount_display_price      = $non_default_discount_price['discount_display_price'];
													$base_price                  = $non_default_discount_price['base_price'];
													$variations                  = $non_default_discount_price['variations'];
													// Pro is setting for the discount to be on top or regular and not sale? - Done on the PRO Side
												} else {
													$applied_rule = false;
												}
											}
											break;
									}
								}
								if ( $discount_price < $base_price ) {
									$applied_rule = $rule;
								}
								if ( $applied_rule ) {
									if (
										// Pro removing the rule if on sale and discount lower or higher?
										apply_filters( 'tdw_recheck_get_product_applied_rule', true, $applied_rule, $product_id, $product_id_base, $base_price, $discount_price, $_product->get_regular_price(), $discount_price_over_regular )
										&&
										// Allow developers to remove the discount on the cart (for example for WooCommerce Subscriptions renewals)
										apply_filters( 'tdw_recheck_cart_applied_rule', true, $applied_rule, $cart_item )
									) {
										break;
									} else {
										$applied_rule = false;
									}
								}
							}
							if ( $discount_price < $base_price || $applied_rule ) {
								break;
							}
						}
						if ( $discount_price < $base_price || $applied_rule ) {
							break;
						}
					}
					// Round with the defined WooCommerce decimals
					if ( $applied_rule ) {
						if (
							$discount_price < $base_price
							&&
							( $discount_price > 0 || ( isset( $applied_rule['allows-discount-to-zero'] ) && $applied_rule['allows-discount-to-zero'] ) )
						) {
							$discount_price         = round( $discount_price, wc_get_price_decimals() );
							$discount_display_price = round( $discount_display_price, wc_get_price_decimals() );
							WC()->cart->cart_contents[ $cart_item_key ]['data']->set_price( $discount_price );
							$discount_data = array(
								'applied_rule'           => $applied_rule,
								'base_price'             => $base_price,
								'display_price'          => $display_price,
								'discount_price'         => $discount_price,
								'discount_display_price' => $discount_display_price,
							);
							WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts'] = $discount_data;
						} else {
							WC()->cart->cart_contents[ $cart_item_key ]['data']->set_price( $base_price );
							unset( WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts'] );
						}
					}
				}
				// Aggregated variations BETA - Not tested for recursion with other non-aggregated variations rules
				if ( count( $variations ) > 0 ) {
					foreach ( $discount_rules as $priority => $terms ) { // We cannot use the helper here
						foreach ( $terms as $term_id => $rules ) {
							foreach ( $rules as $rule_key => $rule ) {
								if ( self::valid_rule_user_role( $rule ) && self::valid_rule_date( $rule ) && isset( $rule['type'] ) && in_array( $rule['type'], $this->rules_aggregate_variations, true ) && isset( $rule['aggr-var'] ) && $rule['aggr-var'] && isset( $rule['value'] ) && is_numeric( $rule['value'] ) && floatval( $rule['value'] ) > 0 ) {
									if ( isset( $variations[ $term_id ][ $rule_key ] ) && is_array( $variations[ $term_id ][ $rule_key ] ) && count( $variations[ $term_id ][ $rule_key ] ) > 0 ) {
										foreach ( $variations[ $term_id ][ $rule_key ] as $temp_id_product => $temp_product ) {
											$rule_applied_for_product = false;
											if ( $temp_product['quantity'] >= floatval( $rule['min-qtt'] ) ) {
												// Apply discount to all instances of this variation
												foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
													$_product = $cart->cart_contents[ $cart_item_key ]['data'];
													if ( in_array( $cart_item_key, $cart_item_keys_variations, true ) ) { // Just double test if this is a variation
														$product_id_base = $_product->get_parent_id();
														if ( intval( $product_id_base ) === intval( $temp_id_product ) ) { // Product match, do it
															$base_price    = (
																isset( WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts'] )
																?
																WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts']['base_price']
																:
																WC()->cart->cart_contents[ $cart_item_key ]['data']->get_price() // Keep compatibility with other plugins that might mess with the cart value
															);
															$display_price = (
																isset( WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts'] )
																?
																WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts']['display_price']
																: (
																	get_option( 'woocommerce_tax_display_cart' ) === 'excl'
																	?
																	wc_get_price_excluding_tax( $_product )
																	:
																	wc_get_price_including_tax( $_product )
																)
															);
															// Normal
															$discount_price         = $base_price;
															$discount_display_price = $display_price;
															switch ( $rule['type'] ) {
																case 'percentage':
																	$discount_price         = $base_price - ( $base_price * ( floatval( $rule['value'] ) / 100 ) );
																	$discount_display_price = $display_price - ( $display_price * ( floatval( $rule['value'] ) / 100 ) );
																	break;
																case 'x-for-y':
																	// Not allowed for aggregate variations - We should never get here because of the if condition above, but just in case
																	break;
																default:
																	// The pro should return false or an array with discount_price and discount_price_over_regular
																	$non_default_discount_price = apply_filters( 'tdw_on_calculate_totals_non_default_rule_aggregate_variation_price', false, $rule, $base_price, $display_price );
																	if ( $non_default_discount_price ) {
																		$discount_price         = $non_default_discount_price['discount_price'];
																		$discount_display_price = $non_default_discount_price['discount_display_price'];
																	}
																	break;
															}
															if (
																$discount_price < $base_price
																&&
																( $discount_price > 0 || ( isset( $rule['allows-discount-to-zero'] ) && $rule['allows-discount-to-zero'] ) )
															) {
																$rule_applied_for_product = true;
																$applied_rule             = $rule;
																WC()->cart->cart_contents[ $cart_item_key ]['data']->set_price( $discount_price ); // Duplicates the discount on each cart load - NOP
																$discount_data = array(
																	'applied_rule' => $applied_rule,
																	'base_price' => $base_price,
																	'display_price' => $display_price,
																	'discount_price' => $discount_price,
																	'discount_display_price' => $discount_display_price,
																);
																WC()->cart->cart_contents[ $cart_item_key ]['taxonomy_discounts'] = $discount_data;
															}
														}
													}
												}
												// Avoid recursion - If a rule was applied we'll just unset any other rules/variations for this same product
												if ( $rule_applied_for_product ) {
													foreach ( $variations as $temp2_term_id => $temp2_rules ) {
														foreach ( $temp2_rules as $temp2_rulekey => $temp2_products ) {
															foreach ( $temp2_products as $temp2_id_product => $temp2_product ) {
																if ( intval( $temp2_id_product ) === intval( $temp_id_product ) ) {
																	unset( $variations[ $temp2_term_id ][ $temp2_rulekey ][ $temp2_id_product ] );
																}
															}
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
				} // Aggregated variations
			}
		}
	}

	/**
	 * Display discounted prices in cart item rows
	 *
	 * Shows original and discounted prices with strikethrough formatting for cart items with applied discounts
	 *
	 * @param string $html      The original price HTML.
	 * @param array  $cart_item The cart item data.
	 * @return string           Modified HTML with discount pricing markup.
	 */
	public function on_display_cart_item_price_html( $html, $cart_item ) {
		if ( isset( $cart_item['taxonomy_discounts'] ) ) {
			$html = '<del>' . wc_price( $cart_item['taxonomy_discounts']['display_price'] ) . '</del><ins> ' . wc_price( $cart_item['taxonomy_discounts']['discount_display_price'] ) . '</ins>';
		}
		return $html;
	}

	/**
	 * Display discounted subtotals in cart
	 *
	 * Shows line item subtotals with discount formatting. Disabled by default since version 5.0
	 * unless activated via the 'tdw_cart_item_subtotal_information' filter. Handles all tax display scenarios
	 *
	 * @param string $html          The original subtotal HTML.
	 * @param array  $cart_item     The cart item data.
	 * @param string $cart_item_key The cart item key.
	 * @return string               Modified HTML with discount subtotal markup.
	 */
	public function on_display_cart_item_subtotal_html( $html, $cart_item, $cart_item_key ) {
		if ( apply_filters( 'tdw_cart_item_subtotal_information', false ) ) {
			if ( isset( $cart_item['taxonomy_discounts'] ) ) {
				$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				// We are replicating WC_Cart::get_product_subtotal()
				$price = $cart_item['taxonomy_discounts']['discount_price'];
				if ( $_product->is_taxable() ) {

					if ( WC()->cart->display_prices_including_tax() ) {
						$row_price        = (float) $cart_item['taxonomy_discounts']['discount_display_price'] * (float) $cart_item['quantity'];
						$product_subtotal = wc_price( $row_price );

						if ( ! wc_prices_include_tax() && WC()->cart->get_subtotal_tax() > 0 ) {
							$product_subtotal .= ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
						}
					} else {
						if ( get_option( 'woocommerce_prices_include_tax' ) === 'yes' ) {
							// Fix Prices inserted with tax and shown without tax
							$price = $cart_item['taxonomy_discounts']['discount_display_price'];
						} else {
							$price = $cart_item['taxonomy_discounts']['discount_price'];
						}
						$row_price        = (float) $price * (float) $cart_item['quantity'];
						$product_subtotal = wc_price( $row_price );

						if ( wc_prices_include_tax() && WC()->cart->get_subtotal_tax() > 0 ) {
							$product_subtotal .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
						}
					}
				} else {
					$row_price        = (float) $price * (float) $quantity;
					$product_subtotal = wc_price( $row_price );
				}
				$html = '<del>' . $html . '</del><ins> ' . $product_subtotal . '</ins>';
			}
		}
		return $html;
	}

	/**
	 * Apply discounts to variation prices in product page
	 *
	 * Modifies variation data to display discounted prices on product pages. Only applies to non-quantity-based rules
	 * We might me missing checking over regular or over promotional...
	 *
	 * @param array                $data      The variation data array.
	 * @param WC_Product           $product   The parent product object.
	 * @param WC_Product_Variation $variation The variation object.
	 * @return array                          Modified variation data with discounted prices.
	 */
	public function woocommerce_available_variation( $data, $product, $variation ) {
		if ( is_product() ) {
			$discount_rules = self::get_discount_rules( true );
			if ( count( $discount_rules ) ) {
				$product_id      = $product->get_id();
				$variation_id    = $variation->get_id();
				$variation_price = floatval( $variation->get_price() );
				$sale_price      = $variation_price;
				$applied_rule    = false;
				foreach ( $discount_rules as $priority => $terms ) { // We cannot use the helper here
					foreach ( $terms as $term_id => $rules ) {
						foreach ( $rules as $rule_key => $rule ) {
							if ( self::valid_rule_user_role( $rule ) && self::valid_rule_date( $rule ) && isset( $rule['type'] ) && has_term( $term_id, $rule['taxonomy'], $product_id ) ) {
								switch ( $rule['type'] ) {
									case 'percentage':
										if ( isset( $rule['value'] ) && is_numeric( $rule['value'] ) && $rule['value'] > 0 ) {
											if ( floatval( $rule['min-qtt'] ) === (float) 0 || floatval( $rule['min-qtt'] ) === (float) 1 ) {
												$sale_price   = $variation_price - ( $variation_price * ( floatval( $rule['value'] ) / 100 ) );
												$applied_rule = $rule;
											}
										}
										break;
									case 'x-for-y':
										// This is a quantity based rule. No way to get the price at this time. Only in cart...
										break;
									default:
										// Allow PRO to set the applied rule
										$discount_price = apply_filters( 'tdw_on_get_price_non_default_rule_discount_price', false, $rule, $variation_price, $variation, true, 1 );
										if ( $discount_price ) {
											$sale_price   = $discount_price['discount_price'];
											$applied_rule = apply_filters( 'tdw_on_calculate_totals_non_default_rule_applied_rule', false, $rule );
										}
										break;
								}
							}
							if ( $sale_price < $variation_price || $applied_rule ) {
								break;
							}
						}
						if ( $sale_price < $variation_price || $applied_rule ) {
							break;
						}
					}
					if ( $sale_price < $variation_price || $applied_rule ) {
						break;
					}
				}
				if (
					$sale_price < $variation_price
					&&
					( $sale_price > 0 || ( isset( $applied_rule['allows-discount-to-zero'] ) && $applied_rule['allows-discount-to-zero'] ) )
				) {
					$show_variation_price = true;
					$variation->set_price( $sale_price );
					// Change variation data
					$data['price']      = $sale_price;
					$data['price_html'] = $show_variation_price ? '<span class="price">' . $variation->get_price_html() . '</span>' : '';
				}
			}
		}
		return $data;
	}

	/**
	 * Apply discounts to variation price ranges
	 *
	 * Modifies the cached variation prices array to show discounted prices in variation price ranges
	 *
	 * @param array      $transient_cached_prices_array_price_hash The cached variation prices array.
	 * @param WC_Product $product                                   The parent product object.
	 * @return array                                                Modified prices array with discounts applied.
	 */
	public function woocommerce_variation_prices( $transient_cached_prices_array_price_hash, $product ) {
		$rule = self::get_product_applied_rule( $product->get_id() );
		if ( ! empty( $rule ) ) {
			// Only if a rule applies to parent
			foreach ( $transient_cached_prices_array_price_hash['price'] as $id_variation => $value ) {
				$transient_cached_prices_array_price_hash['price'][ $id_variation ] = wc_format_decimal( self::on_get_price( $value, wc_get_product( $id_variation ), true ), wc_get_price_decimals() );
			}
		}
		return $transient_cached_prices_array_price_hash;
	}

	/**
	 * Apply discounts to variation sale prices
	 *
	 * Modifies the sale price of variations to reflect discounts from applicable rules
	 *
	 * UpStore theme, sales badge compatibility - Testing for lampicris.com
	 *
	 * @param float      $price   The original variation sale price.
	 * @param WC_Product $product The variation product object.
	 * @return float              Modified sale price with discounts applied.
	 */
	public function woocommerce_get_variation_sale_price( $price, $product ) {
		$rule = self::get_product_applied_rule( $product->get_id() );
		if ( ! empty( $rule ) ) {
			$price = wc_format_decimal( self::on_get_price( $price, $product, true ), wc_get_price_decimals() );
		}
		return $price;
	}

	/**
	 * Validate fixed cart coupon usage with discount rules
	 *
	 * Prevents fixed cart coupons from being applied when discount rules have coupons disabled
	 *
	 * @param bool      $valid  Current validity status.
	 * @param WC_Coupon $coupon The coupon object being validated.
	 * @return bool             Whether the coupon is valid for use.
	 */
	public function woocommerce_fixed_cart_coupon_is_valid( $valid, $coupon ) {
		if ( $valid ) {
			if ( $coupon->is_type( 'fixed_cart' ) ) {
				if ( count( WC()->cart->get_cart() ) > 0 ) {
					foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
						if ( isset( $cart_item['taxonomy_discounts'] ) && $cart_item['taxonomy_discounts']['applied_rule']['disable_coupon'] ) {
							return false;
						}
					}
				}
			}
		}
		return $valid;
	}

	/**
	 * Adjust coupon discount amount based on discount rules
	 *
	 * Prevents additional coupon discounts on items where discount rules have coupons disabled
	 *
	 * @param float $discount           The coupon discount amount.
	 * @param float $discounting_amount The amount being discounted.
	 * @param array $cart_item          The cart item data.
	 * @return float                    Modified discount amount (0 if coupons disabled, otherwise original amount).
	 */
	public function woocommerce_coupon_get_discount_amount( $discount, $discounting_amount, $cart_item ) {
		// if ( !$coupon->is_type( 'fixed_cart' ) ) { // On fixed cart coupons we should allow the discount, or the final coupon
		if ( floatval( $discount ) > 0 ) {
			if ( isset( $cart_item['taxonomy_discounts'] ) && $cart_item['taxonomy_discounts']['applied_rule']['disable_coupon'] ) {
				$discount = 0;
			}
		}
		// }
		return $discount;
	}

	/**
	 * Determine if a product is on sale including taxonomy discounts
	 *
	 * Checks if a product should be marked as "on sale" based on applied discount rules
	 *
	 * @param bool       $is_on_sale Current on-sale status.
	 * @param WC_Product $product    The product object.
	 * @return bool                  True if product should display as on sale, false otherwise.
	 */
	public function on_get_product_is_on_sale( $is_on_sale, $product ) {
		if ( $this->debug ) {
			do_action( 'qm/start', 'WC_Taxonomy_Discounts_Webdados::on_get_product_is_on_sale - ' . $product->get_id() );
		}
		// Cached?
		if ( $this->enable_cache && isset( $this->cache_on_sale[ $product->get_id() ] ) ) {
			if ( $this->debug ) {
				do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_product_is_on_sale - ' . $product->get_id() );
			}
			return $this->cache_on_sale[ $product->get_id() ];
		}
		// Already on sale?
		if ( $is_on_sale || apply_filters( 'tdw_disable_is_on_sale_filter', false ) ) {
			$this->cache_on_sale[ $product->get_id() ] = $is_on_sale; // Set cache
			if ( $this->debug ) {
				do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_product_is_on_sale - ' . $product->get_id() );
			}
			return $is_on_sale;
		}
		if ( $this->debug ) {
			do_action( 'qm/lap', 'WC_Taxonomy_Discounts_Webdados::on_get_product_is_on_sale - ' . $product->get_id() );
		}
		// Check rules
		if ( $product->has_child() ) {
			$is_on_sale = false;
			$prices     = $product->get_variation_prices();
			if ( $prices['price'] !== $prices['regular_price'] ) {
				$is_on_sale = true;
			} else {
				// Try testing with global product price - This may not be 100% accurate
				$global_price = floatval( wc_format_decimal( $product->get_price(), wc_get_price_decimals() ) );
				$min_price    = 0;
				if ( $global_price > 0 ) {
					foreach ( $prices['price'] as $temp_price ) {
						if ( floatval( $temp_price ) < $min_price || floatval( $min_price ) === (float) 0 ) {
							$min_price = floatval( $temp_price );
						}
					}
					if ( $global_price < $min_price ) {
						$is_on_sale = true;
					}
				}
			}
		} else {
			// We remove and re-add the filter to avoid a duplicate call that we're not sure why happens, but applies the discount twice and we might get incorrect "on sale" results if the price goes to zero
			remove_filter( $this->get_price_filter, array( &$this, 'on_get_price' ), $this->get_price_filter_priority, 2 );
			$discount_price = self::on_get_price( wc_format_decimal( $product->get_price(), wc_get_price_decimals() ), $product, true );
			add_filter( $this->get_price_filter, array( &$this, 'on_get_price' ), $this->get_price_filter_priority, 2 );
			if ( $this->debug ) {
				do_action( 'qm/lap', 'WC_Taxonomy_Discounts_Webdados::on_get_product_is_on_sale - ' . $product->get_id() );
			}
			$regular_price               = wc_format_decimal( $product->get_regular_price(), wc_get_price_decimals() );
			$product_to_get_applied_rule = ( $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product );
			$applied_rule                = self::get_product_applied_rule( $product_to_get_applied_rule ); // Here we need to get the applied rule for the parent, if a variation
			if (
				( empty( $regular_price ) || empty( $discount_price ) )
				&&
				// No Applied rule or Applied rule that does NOT allow discount to zero
				( ! $applied_rule || ( is_array( $applied_rule ) && ! $applied_rule['allows-discount-to-zero'] ) )
			) {
				$this->cache_on_sale[ $product->get_id() ] = $is_on_sale; // Set cache
				if ( $this->debug ) {
					do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_product_is_on_sale - ' . $product->get_id() );
				}
				return $is_on_sale;
			} else {
				$is_on_sale = wc_format_decimal( $regular_price, wc_get_price_decimals() ) > wc_format_decimal( $discount_price, wc_get_price_decimals() );
			}
			if ( $this->debug ) {
				do_action( 'qm/lap', 'WC_Taxonomy_Discounts_Webdados::on_get_product_is_on_sale - ' . $product->get_id() );
			}
		}
		$this->cache_on_sale[ $product->get_id() ] = $is_on_sale; // Set cache
		if ( $this->debug ) {
			do_action( 'qm/stop', 'WC_Taxonomy_Discounts_Webdados::on_get_product_is_on_sale - ' . $product->get_id() );
		}
		return $is_on_sale;
	}

	/**
	 * Display discount information on product loop/archive pages
	 *
	 * Wrapper method to show discount details in product listings
	 */
	public function discount_information_loop() {
		$this->discount_information( 'loop' );
	}

	/**
	 * Display discount information on single product pages
	 *
	 * Wrapper method to show discount details on individual product pages
	 */
	public function discount_information_single() {
		$this->discount_information( 'single' );
	}

	/**
	 * Discount information
	 *
	 * @param string $location The location where the discount information is shown.
	 */
	private function discount_information( $location ) {
		global $product;
		$info           = '';
		$found          = false;
		$discount_rules = self::get_discount_rules( true );
		$product_id     = $product->get_id();
		if ( count( $discount_rules ) ) {
			$rule = self::get_product_applied_rule( $product );
			if ( ! empty( $rule ) ) {
				ob_start();
				switch ( $rule['type'] ) {
					case 'percentage':
						if ( isset( $rule['value'] ) && is_numeric( $rule['value'] ) && $rule['value'] > 0 ) {
							$found = true;
							if ( floatval( $rule['min-qtt'] ) === (float) 0 || floatval( $rule['min-qtt'] ) === (float) 1 ) {
								/* translators: %d: Discount percentage */
								$info = wp_kses_post(
									sprintf(
										apply_filters(
											'tdw_text_x_discount',
											/* translators: %d: Rule percentage */
											__( '<span class="tdw_discount_information_percentage">%d%%</span> discount', 'taxonomy-discounts-woocommerce' ),
											$location,
											$rule
										),
										intval( $rule['value'] )
									)
								);
							} else {
								$info = wp_kses_post(
									sprintf(
										apply_filters(
											'tdw_text_from_x_bought_y_discount',
											/* translators: %1$d: Minimum quantity, %2$d: Rule percentage */
											__( 'From <span class="tdw_discount_information_min_qty">%1$d</span> bought, <span class="tdw_discount_information_percentage">%2$d%%</span> discount', 'taxonomy-discounts-woocommerce' ),
											$location,
											$rule
										),
										floatval( $rule['min-qtt'] ),
										intval( $rule['value'] )
									)
								);
							}
						}
						break;
					case 'x-for-y':
						if ( isset( $rule['x'] ) && is_numeric( $rule['x'] ) && floatval( $rule['x'] ) > 0 && isset( $rule['y'] ) && is_numeric( $rule['y'] ) && floatval( $rule['y'] ) > 0 && floatval( $rule['x'] ) > floatval( $rule['y'] ) ) {
							$found = true;
							/* translators: 1: Quantity bought 2: Quantity free */
							$info = wp_kses_post(
								sprintf(
									apply_filters(
										'tdw_text_for_each_x_bought_y_free',
										/* translators: %1$s: Quantity bought, %2$s: Quantity free */
										__( 'For each <span class="tdw_discount_information_qty_bought">%1$s</span> bought <span class="tdw_discount_information_qty_free">%2$s</span> is free', 'taxonomy-discounts-woocommerce' ),
										$location,
										$rule
									),
									$rule['x'],
									$rule['y']
								)
							);
						}
						break;
					default:
						// Non default rule types - Allow PRO or custom code to set the information text through filters
						$info = apply_filters( 'tdw_discount_information_non_default_rule', '', $product, $rule, $location );
						break;
				}
				if ( trim( $info ) !== '' ) {
					?>
					<div class="wctd_discount_information"><?php echo wp_kses_post( $info ); ?></div>
					<?php
				}
				// Echo passing through filters
				echo wp_kses_post(
					apply_filters(
						'tdw_discount_information_' . $product->get_id(),
						apply_filters(
							'tdw_discount_information',
							ob_get_clean(),
							$product,
							$rule,
							$location
						),
						$product,
						$rule,
						$location
					)
				);
			}
		}
		// No rule found - Try to add to non-taxonomy discounts
		if ( ! $found ) {
			// Echo passing through filter
			echo wp_kses_post(
				apply_filters(
					'tdw_discount_information',
					'',
					$product,
					null,
					$location
				)
			);
		}
	}

	/**
	 * Customize sale badge with discount percentage
	 *
	 * Modifies the WooCommerce sale badge to display the actual discount percentage
	 *
	 * @param string     $html      The original sale badge HTML.
	 * @param WP_Post    $post      The product post object.
	 * @param WC_Product $product   The product object.
	 * @param bool       $only_text Whether to return only text (for blocks) or full HTML.
	 * @return string             Modified sale badge HTML with percentage or original HTML.
	 */
	public function woocommerce_sale_flash( $html, $post, $product, $only_text = false ) {
		$new_html = false;
		$rule     = self::get_product_applied_rule( $product );
		if ( ( ! empty( $rule ) ) && apply_filters( 'tdw_perc_sale_badge_' . $product->get_id(), true ) ) {
			switch ( $rule['type'] ) {
				case 'percentage':
					if ( isset( $rule['value'] ) && is_numeric( $rule['value'] ) && $rule['value'] > 0 ) {
						$found = true;
						if ( floatval( $rule['min-qtt'] ) === (float) 0 || floatval( $rule['min-qtt'] ) === (float) 1 ) {
							$new_html = sprintf( '-%d%%', intval( $rule['value'] ) );
						}
						// We need to keep it short
						// else {
						// $new_html = sprintf( __( 'From <strong>%d</strong> bought, <strong>%d%%</strong> discount', 'taxonomy-discounts-woocommerce' ), floatval( $rule['min-qtt'] ), intval( $rule['value'] ) );
						// }
					}
					break;
				// We need to keep it short
				// case 'x-for-y':
					// if ( isset( $rule['x'] ) && is_numeric( $rule['x'] ) && floatval( $rule['x'] ) > 0 && isset( $rule['y'] ) && is_numeric( $rule['y'] ) && floatval( $rule['y'] ) > 0 && floatval(    $rule['x'] ) > floatval( $rule['y'] ) ) {
					// $found = true;
					// $info = sprintf( __( 'For each <strong>%s</strong> bought <strong>%s</strong> is free', 'taxonomy-discounts-woocommerce' ), $rule['x'], $rule['y'] );
					// }
					// break;
				default:
					// PRO integration - Done with the tdw_perc_sale_badge_html filter below
					break;
			}
		}
		$new_html = apply_filters( 'tdw_perc_sale_badge_html', $new_html, $product, $rule );
		if ( $new_html ) {
			if ( $only_text ) {
				$html = $new_html;
			} elseif ( $this->flatsome_active ) {
				$search = '/(<span class="onsale">).*?(<\/span>)/';
				$html   = preg_replace( $search, '<span class="onsale">' . $new_html . '</span>', $html );
			} else {
				$html = '<span class="onsale">' . $new_html . '</span>';
			}
		}
		return $html;
	}

	/**
	 * Customize sale badge text for blocks
	 *
	 * Modifies the sale badge text when only text is requested (e.g. for Gutenberg blocks) to show discount percentage
	 *
	 * @param string     $text    The original sale badge text.
	 * @param WC_Product $product The product object.
	 * @return string             Modified sale badge text with percentage or original text.
	 */
	public function woocommerce_sale_badge_text( $text, $product ) {
		return $this->woocommerce_sale_flash( $text, null, $product, true );
	}

	/**
	 * Render the admin settings page
	 *
	 * Displays the discount rules management interface including add/edit forms and rules table
	 */
	public function admin_page() {
		?>
		<div class="wrap woocommerce tdw">

			<h1>
				<?php esc_html_e( 'Taxonomy/Term and Role based Discounts for WooCommerce', 'taxonomy-discounts-woocommerce' ); ?> <?php echo esc_html( $this->version ); ?>
				<?php do_action( 'tdw_admin_after_title' ); ?>
			</h1>

			<p>
				<a href="https://wordpress.org/support/plugin/taxonomy-discounts-woocommerce/reviews/?filter=5" target="_blank">&starf;&starf;&starf;&starf;&starf; <?php esc_html_e( 'Rate this plugin on WordPress.org', 'taxonomy-discounts-woocommerce' ); ?></a>
				|
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %1$s: open html tag, %2$s: close html tag, %3$s: open html tag, %4$s: close html tag */
						__( 'Check out our other %1$sfree%2$s and %3$spremium plugins%4$s', 'taxonomy-discounts-woocommerce' ),
						'<a href="https://profiles.wordpress.org/webdados/#content-plugins" target="_blank">',
						'</a>',
						'<a href="https://nakedcatplugins.com" target="_blank">',
						'</a>'
					)
				);
				?>
				<?php if ( apply_filters( 'tdw_admin_show_pro_link', true ) ) { ?>
					|
					<a href="<?php echo esc_url( $this->pro_addon_link ); ?>" target="_blank"><strong style="background-color: yellow; color: black; padding: 0.5em;"><?php esc_html_e( 'Buy the PRO add-on', 'taxonomy-discounts-woocommerce' ); ?></strong></a>
				<?php } ?>
			</p>

			<?php do_action( 'tdw_admin_before_discount_rules_table' ); ?>

			<?php if ( apply_filters( 'tdw_admin_show_pro_link', true ) ) { ?>
				<div id="tdw-proaddon-features">
					<h2><?php esc_html_e( 'PRO add-on features', 'taxonomy-discounts-woocommerce' ); ?>:</h2>
					<ul>
						<li><?php esc_html_e( '"Discount Tag" custom taxonomy if you don\'t want to use Categories, Tags or any other existing product taxonomy', 'taxonomy-discounts-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Set maximum amount of free items when using BOGO discounts', 'taxonomy-discounts-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Replace sale badge with discount percentage', 'taxonomy-discounts-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Show discount information (percentage and dates) on the product loop', 'taxonomy-discounts-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Show discount information (percentage and dates) on the product single page', 'taxonomy-discounts-woocommerce' ); ?></li>
						<li><?php esc_html_e( '"Stop - no discount" rule that makes sure products from specific taxonomy terms never have a discount applied, even if there are other rules that will apply for other product taxonomy terms', 'taxonomy-discounts-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Exclude products already on sale from the discount rule', 'taxonomy-discounts-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Disable shipping methods based on cart items applied rules', 'taxonomy-discounts-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Set discount rules for non-logged-in users', 'taxonomy-discounts-woocommerce' ); ?></li>
						<li>
							<?php esc_html_e( 'Developer mode', 'taxonomy-discounts-woocommerce' ); ?>:
							<ul>
								<li><?php esc_html_e( 'Set unique ID per discount rule for identification on custom code', 'taxonomy-discounts-woocommerce' ); ?></li>
								<li><?php esc_html_e( 'More to come...', 'taxonomy-discounts-woocommerce' ); ?></li>
							</ul>
						</li>
						<li><?php esc_html_e( 'Technical support', 'taxonomy-discounts-woocommerce' ); ?></li>
					</ul>
					<p><a href="<?php echo esc_url( $this->pro_addon_link ); ?>" target="_blank" class="button-primary"><?php esc_html_e( 'Buy now', 'taxonomy-discounts-woocommerce' ); ?></a></p>
				</div>
			<?php } ?>

			<div id="tdw-rules-table">
				<?php self::admin_page_rules_table(); ?>
			</div>

			<?php do_action( 'tdw_admin_after_discount_rules_table' ); ?>

			<?php
			$taxonomy_objects = self::get_product_taxonomies();
			if ( count( $taxonomy_objects ) > 0 ) {
				?>
				<div id="tdw-form-add-div">
					<form id="tdw-form-add" method="post">
						<h2><?php esc_html_e( 'Add new Taxonomy/Term based discount rule:', 'taxonomy-discounts-woocommerce' ); ?></h2>

						<?php
						if ( $this->wpml_active ) {
							global $sitepress;
							$languages    = icl_get_languages( 'skip_missing=0&orderby=code' );
							$current_lang = $sitepress->get_language_details( $sitepress->get_current_language() );
							?>
							<div id="tdw-form-add-div-wpml">
								<p>
									<img class="icl_als_iclflag" src="<?php echo esc_url( $languages[ $sitepress->get_current_language() ]['country_flag_url'] ); ?>" width="18" height="12">
									<strong>
									<?php
									echo wp_kses_post(
										sprintf(
											/* translators: %s: Language */
											__( 'Currently adding rules in %s', 'taxonomy-discounts-woocommerce' ),
											$current_lang['display_name']
										)
									);
									?>
									</strong>
								</p>
								<p><?php esc_html_e( 'If you want the discount to be applied to all languages, you need to create rules for each term translation', 'taxonomy-discounts-woocommerce' ); ?></p>
							</div>
							<?php
						}
						?>

						<!-- Taxonomy and term -->
						<div id="tdw-form-add-div-1">
							<p class="tdw-float-left">
								<label for="tdw-form-add-taxonomy"><strong><?php esc_html_e( 'Taxonomy', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
								<br>
								<?php
								$add_taxonomy_options = array();
								foreach ( $taxonomy_objects as $tax => $taxonomy ) {
									$add_taxonomy_options[ $tax ] = array(
										'label'    => $taxonomy->labels->singular_name . ' (' . $tax . ')',
										'taxonomy' => $taxonomy,
									);
								}
								$add_taxonomy_options = apply_filters( 'tdw_admin_add_form_taxonomies_options', $add_taxonomy_options );
								?>
								<select id="tdw-form-add-taxonomy" name="tdw-form-add-taxonomy">
									<option value="">- &nbsp;<?php esc_html_e( 'choose', 'taxonomy-discounts-woocommerce' ); ?>&nbsp; -</option>
									<?php
									foreach ( $add_taxonomy_options as $tax => $taxonomy ) {
										?>
										<option value="<?php echo esc_attr( $tax ); ?>">
											<?php echo esc_html( $taxonomy['label'] ); ?>
											<?php do_action( 'tdw_admin_after_taxonomy_name', $tax, $taxonomy['taxonomy'] ); ?>
										</option>
										<?php
									}
									?>
								</select>
							</p>
							<p id="tdw-form-add-choose-term" class="tdw-float-left">
							</p>
						</div>
						<div class="clear"></div>

						<!-- Discount configuration -->
						<div id="tdw-form-add-div-2" class="tdw-hidden">
							<p id="tdw-form-add-choose-role" class="tdw-float-left">
								<label for="tdw-form-add-type"><strong><?php esc_html_e( 'User role', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
								<br>
								<?php self::wp_dropdown_roles( 'add', '' ); ?>
							</p>
							<div class="clear"></div>
							<p id="tdw-form-add-choose-type" class="tdw-float-left">
								<label for="tdw-form-add-type"><strong><?php esc_html_e( 'Discount type', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
								<br>
								<select id="tdw-form-add-type" name="tdw-form-add-type">
									<option value="">- &nbsp;<?php esc_html_e( 'choose', 'taxonomy-discounts-woocommerce' ); ?>&nbsp; -</option>
									<?php
									foreach ( $this->discount_types as $discount_type ) {
										?>
										<option value="<?php echo esc_attr( $discount_type ); ?>"><?php echo esc_html( self::get_rule_type_name( $discount_type, true ) ); ?></option>
										<?php
									}
									?>
								</select>
							</p>
							<p id="tdw-form-add-choose-type-percentage" class="tdw-float-left tdw-hidden tdw-hide-empty-type">
								<label><strong><?php esc_html_e( 'Min. Qtt.', 'taxonomy-discounts-woocommerce' ); ?> / <?php esc_html_e( 'Discount', 'taxonomy-discounts-woocommerce' ); ?> / <?php esc_html_e( 'Aggregate variations', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
								<br>
								<span><input type="number" id="tdw-form-add-percentage-min-qtt" name="tdw-form-add-percentage-min-qtt" min="0" step="1" placeholder="0"></span>
								/
								<span><input type="number" id="tdw-form-add-percentage-value" name="tdw-form-add-percentage-value" min="1" max="99" step="1" placeholder="0" class="required">%</span>
								/
								<span>
									<select id="tdw-form-add-percentage-aggr-var" name="tdw-form-add-percentage-aggr-var">
										<option value="0"><?php esc_html_e( 'No', 'taxonomy-discounts-woocommerce' ); ?></option>
										<option value="1"><?php esc_html_e( 'Yes', 'taxonomy-discounts-woocommerce' ); ?></option>
									</select>
									<?php
									echo wp_kses_post(
										wc_help_tip(
											sprintf(
												/* translators: %1$s: strong open tag, %2$s: Aggregate variations, %3$s: strong close tag */
												__( '%1$s%2$s%3$s: if enabled, the quantity will be the sum of all the variations of a product in the cart.', 'taxonomy-discounts-woocommerce' ),
												'<strong>',
												__( 'Aggregate variations', 'taxonomy-discounts-woocommerce' ),
												'</strong>'
											),
											true
										)
									);
									?>
								</span>
							</p>
							<p id="tdw-form-add-choose-type-x-for-y" class="tdw-float-left tdw-hidden tdw-hide-empty-type">
								<label><strong>
									<?php
									echo wp_kses_post(
										sprintf(
											/* translators: %1$s: x, %2$s: y */
											__( 'For each <strong>%1$s</strong> bought <strong>%2$s</strong> is free', 'taxonomy-discounts-woocommerce' ),
											'x',
											'y'
										)
									);
									?>
								</strong>:</label>
								<br>
								<span><input type="number" id="tdw-form-add-x-for-y-x" name="tdw-form-add-x-for-y-x" min="1" step="1" placeholder="x" class="required"></span>
								/
								<span><input type="number" id="tdw-form-add-x-for-y-y" name="tdw-form-add-x-for-y-y" min="1" step="1" placeholder="y" class="required"></span>
								<?php do_action( 'tdw_admin_after_x_for_y_add_form' ); ?>
							</p>
							<?php do_action( 'tdw_admin_after_discount_add_form' ); ?>
						</div>
						<div class="clear"></div>

						<!-- Other settings -->
						<div id="tdw-form-add-div-3" class="tdw-hidden">
							<p id="tdw-form-add-choose-priority" class="tdw-float-left">
								<label for="tdw-form-add-priority"><strong><?php esc_html_e( 'Priority', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
								<br>
								<input type="number" id="tdw-form-add-priority" name="tdw-form-add-priority" min="1" max="999" step="1" class="required">
							</p>
							<p id="tdw-form-add-choose-disable-coupon" class="tdw-float-left" title="<?php esc_attr_e( 'Disable extra coupon discounts', 'taxonomy-discounts-woocommerce' ); ?>">
								<label for="tdw-form-add-disable-coupon"><strong><?php esc_html_e( 'Disable coupons', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
								<br>
								<select id="tdw-form-add-disable-coupon" name="tdw-form-add-disable-coupon">
									<option value="1"><?php esc_html_e( 'Yes', 'taxonomy-discounts-woocommerce' ); ?></option>
									<option value="0"><?php esc_html_e( 'No', 'taxonomy-discounts-woocommerce' ); ?></option>
								</select>
							</p>
						</div>
						<div class="clear"></div>

						<!-- Active settings -->
						<div id="tdw-form-add-div-4" class="tdw-hidden">
							<p id="tdw-form-add-choose-active" class="tdw-float-left">
								<label for="tdw-form-add-priority"><strong><?php esc_html_e( 'Active', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
								<br>
								<select id="tdw-form-add-active" name="tdw-form-add-active">
									<option value="1"><?php esc_html_e( 'Yes', 'taxonomy-discounts-woocommerce' ); ?></option>
									<option value="0"><?php esc_html_e( 'No', 'taxonomy-discounts-woocommerce' ); ?></option>
								</select>
							</p>
							<p id="tdw-form-add-choose-from" class="tdw-float-left">
								<label for="tdw-form-add-from"><strong><?php esc_html_e( 'From', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
								<br>
								<input type="text" class="tdw-date-field" name="tdw-form-add-from" id="tdw-form-add-from" placeholder="<?php esc_attr_e( 'yyyy-mm-dd', 'taxonomy-discounts-woocommerce' ); ?>" maxlength="10">
								<?php
								if ( $this->enable_time ) {
									?>
									<input type="text" class="tdw-time-field" name="tdw-form-add-from-time" id="tdw-form-add-from-time" placeholder="00:00:00" maxlength="8"><?php } ?>
							</p>
							<p id="tdw-form-add-choose-to" class="tdw-float-left">
								<label for="tdw-form-add-to"><strong><?php esc_html_e( 'To', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
								<br>
								<input type="text" class="tdw-date-field" name="tdw-form-add-to" id="tdw-form-add-to" placeholder="<?php esc_attr_e( 'yyyy-mm-dd', 'taxonomy-discounts-woocommerce' ); ?>" maxlength="10">
								<?php
								if ( $this->enable_time ) {
									?>
									<input type="text" class="tdw-time-field" name="tdw-form-add-to-time" id="tdw-form-add-to-time" placeholder="23:59:59" maxlength="8"><?php } ?>
							</p>
						</div>
						<div class="clear"></div>

						<?php
						if (
							( defined( 'WCTD_ADVANCED_MODE' ) && WCTD_ADVANCED_MODE )
							||
							apply_filters( 'tdw_dev_mode', false )
						) {
							?>
							<!-- Advanced settings -->
							<div class="tdw-form-add-div-more tdw-hidden">
								<p id="tdw-form-add-advanced-id" class="tdw-float-left">
									<label for="tdw-form-advanced-id"><strong><?php esc_html_e( 'ID', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
									<br>
									<input type="text" name="tdw-form-advanced-id" id="tdw-form-advanced-id" placeholder="<?php esc_attr_e( 'For developers', 'taxonomy-discounts-woocommerce' ); ?>" maxlength="10">
								</p>
							</div>
							<div class="clear"></div>
						<?php } ?>

						<?php do_action( 'tdw_admin_before_submit_add_form' ); ?>

						<!-- Submit -->
						<div>
							<p id="tdw-form-add-choose-submit" class="tdw-float-left tdw-hidden">
								<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save', 'taxonomy-discounts-woocommerce' ); ?>">
								<?php wp_nonce_field( 'tdw_nonce' ); ?>
							</p>
						</div>
						<div class="clear"></div>

					</form>
				</div>
			<?php } ?>

		</div>
		<?php
	}

	/**
	 * Verify security for admin AJAX requests
	 *
	 * Validates user capabilities and nonce for admin AJAX operations
	 *
	 * @return bool True if the request is authorized, false otherwise.
	 */
	private function admin_ajax_secure_calls() {
		return current_user_can( 'manage_woocommerce' ) && isset( $_REQUEST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'tdw_nonce' );
	}
	/**
	 * AJAX handler for refreshing the discount rules table
	 *
	 * Validates the request and re-renders the admin rules table
	 */
	public function admin_page_rules_table_ajax() {
		if ( $this->admin_ajax_secure_calls() ) {
			self::admin_page_rules_table();
		}
		wp_die();
	}

	/**
	 * Render the discount rules table in admin
	 *
	 * Displays all configured discount rules with edit and delete options
	 */
	public function admin_page_rules_table() {
		$discount_rules = self::get_discount_rules( false );
		$priority       = 0;
		if ( $this->wpml_active ) {
			$languages = icl_get_languages( 'skip_missing=0&orderby=code' );
		}
		?>
		<h2><?php esc_html_e( 'Discount rules', 'taxonomy-discounts-woocommerce' ); ?></h2>
		<!--
		<hr>
		<p>
			<a href="#" id="tdw-form-reload">RELOAD</a>
		</p>
		<hr>
		-->
		<?php do_action( 'tdw_admin_before_rules_table' ); ?>
		<form id="tdw-form-edit" method="post">
			<table class="wp-list-table widefat">
				<thead>
					<tr>
						<th title="<?php esc_attr_e( 'Priority', 'taxonomy-discounts-woocommerce' ); ?>" style="text-align: center;"><?php esc_html_e( 'Pri.', 'taxonomy-discounts-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Active', 'taxonomy-discounts-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Taxonomy', 'taxonomy-discounts-woocommerce' ); ?> / <?php esc_html_e( 'Term', 'taxonomy-discounts-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'User role', 'taxonomy-discounts-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Type', 'taxonomy-discounts-woocommerce' ); ?> / <?php esc_html_e( 'Discount', 'taxonomy-discounts-woocommerce' ); ?></th>
						<th title="<?php esc_attr_e( 'Disable extra coupon discounts', 'taxonomy-discounts-woocommerce' ); ?>"><?php esc_html_e( 'Dis. coupons', 'taxonomy-discounts-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'From', 'taxonomy-discounts-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'To', 'taxonomy-discounts-woocommerce' ); ?></th>
						<?php
						if (
							( defined( 'WCTD_ADVANCED_MODE' ) && WCTD_ADVANCED_MODE )
							||
							apply_filters( 'tdw_dev_mode', false )
						) {
							?>
							<th><?php esc_html_e( 'ID', 'taxonomy-discounts-woocommerce' ); ?></th>
						<?php } ?>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( count( $discount_rules ) > 0 ) {
						foreach ( $discount_rules as $priority => $terms ) {
							foreach ( $terms as $term_id => $rules ) {
								foreach ( $rules as $rule ) {
									$taxonomy      = get_taxonomy( $rule['taxonomy'] );
									$taxonomy_name = $taxonomy ? $taxonomy->labels->singular_name : '';
									$term          = self::get_term( $term_id, $rule['taxonomy'] );
									do_action( 'tdw_admin_discount_rules_table_before_rule', $rule );
									?>
									<tr class="tdw-edit-rule-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>">
										<td style="text-align: center;">
											<?php echo intval( $priority ); ?>
											<?php
											if ( $this->wpml_active && $taxonomy ) {
												$language_code = apply_filters(
													'wpml_element_language_code',
													null,
													array(
														'element_id'   => (int) $term->term_taxonomy_id,
														'element_type' => $taxonomy->name,
													)
												);
												if ( ! empty( $language_code ) ) {
													?>
													<br><img class="icl_als_iclflag" src="<?php echo esc_url( $languages[ $language_code ]['country_flag_url'] ); ?>" width="18" height="12">
													<?php
												}
											}
											?>
										</td>
										<td><?php echo $rule['active'] ? '<span class="dashicons dashicons-yes"></span>' : '&nbsp;'; ?></td>
										<td>
											<?php
											echo esc_html( $taxonomy_name );
											do_action( 'tdw_admin_after_taxonomy_name', $rule['taxonomy'], $taxonomy );
											if ( $taxonomy ) {
												$term_name = trim( $term->name );
												if ( $taxonomy->public || $taxonomy->publicly_queryable ) {
													$term_link = get_term_link( $term );
													if ( ! is_wp_error( $term_link ) ) {
														$term_name = '<a href="' . esc_url( $term_link ) . '" target="_blank">' . esc_html( $term_name ) . '</a>';
													}
												}
												echo ' / <strong>' . wp_kses_post( $term_name ) . '</strong> <small title="' . esc_attr__( 'Number of terms in this taxonomy', 'taxonomy-discounts-woocommerce' ) . '">(' . intval( $term->count ) . ')</small>';
											}
											?>
											<div class="row-actions">
												<span class="edit">
													<a href="#" data-meta-id="<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>"><?php esc_html_e( 'Edit', 'taxonomy-discounts-woocommerce' ); ?></a>
													|
												</span>
												<span class="trash deleterule">
													<a href="#" data-meta-id="<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" data-taxonomy="<?php echo esc_attr( $rule['taxonomy'] ); ?>"><?php esc_html_e( 'Delete Permanently', 'taxonomy-discounts-woocommerce' ); ?></a>
												</span>
											</div>
										</td>
										<td>
											<?php
											$user_role = isset( $rule['user_role'] ) && trim( $rule['user_role'] ) !== '' ? trim( $rule['user_role'] ) : '';
											switch ( $user_role ) {
												case '':
												case '_all_users_':
													esc_html_e( 'All users', 'taxonomy-discounts-woocommerce' );
													break;
												case '_logged_in_':
													esc_html_e( 'Logged-in users', 'taxonomy-discounts-woocommerce' );
													break;
												default:
													global $wp_roles;
													if ( isset( $wp_roles->roles[ $user_role ] ) ) {
														echo esc_html( $wp_roles->roles[ $user_role ]['name'] );
													} else {
														echo esc_html( apply_filters( 'tdw_admin_user_role_name', '', $user_role ) );
													}
													break;
											}
											?>
										</td>
										<td>
											<?php echo wp_kses_post( self::get_rule_type_name( $rule['type'] ) ); ?>
											<br>
											<?php
											switch ( $rule['type'] ) {
												case 'percentage':
													if ( floatval( $rule['min-qtt'] ) > 0 ) {
														echo wp_kses_post(
															sprintf(
																/* translators: %1$d: Minimum quantity, %2$d: Rule percentage */
																__( 'From %1$s bought, %2$s%% discount', 'taxonomy-discounts-woocommerce' ),
																'<strong>' . floatval( $rule['min-qtt'] ) . '</strong>',
																'<strong>' . intval( $rule['value'] ) . '</strong>'
															)
														);
													} else {
														echo wp_kses_post(
															sprintf(
																/* translators: %s: Discount percentage */
																__( '%s%% discount', 'taxonomy-discounts-woocommerce' ),
																'<strong>' . intval( $rule['value'] ) . '</strong>'
															)
														);
													}
													if ( isset( $rule['aggr-var'] ) && $rule['aggr-var'] ) {
														echo ', ' . esc_html__( 'aggr. var.', 'taxonomy-discounts-woocommerce' );
													}
													break;
												case 'x-for-y':
													echo wp_kses_post(
														apply_filters(
															'tdw_edit_rule_x_for_y_info',
															sprintf(
																/* translators: 1: Quantity bought 2: Quantity free */
																__( 'For each %1$s bought %2$s is free', 'taxonomy-discounts-woocommerce' ),
																'<strong>' . intval( $rule['x'] ) . '</strong>',
																'<strong>' . intval( $rule['y'] ) . '</strong>'
															),
															$rule
														)
													);
													break;
												default:
													do_action( 'tdw_non_default_rule_type_table_info', $rule );
													break;
											}
											do_action( 'tdw_admin_discount_rules_table_after_discount_type_info', $rule );
											?>
										</td>
										<td><?php echo $rule['disable_coupon'] ? '<span class="dashicons dashicons-yes"></span>' : '&nbsp;'; ?></td>
										<td><?php echo isset( $rule['from'] ) && trim( $rule['from'] ) !== '' ? esc_html( trim( $rule['from'] ) ) : '&infin;'; ?></td>
										<td><?php echo isset( $rule['to'] ) && trim( $rule['to'] ) !== '' ? esc_html( trim( $rule['to'] ) ) : '&infin;'; ?></td>
										<?php
										if (
											( defined( 'WCTD_ADVANCED_MODE' ) && WCTD_ADVANCED_MODE )
											||
											apply_filters( 'tdw_dev_mode', false )
										) {
											?>
											<td><?php echo isset( $rule['advanced_id'] ) && trim( $rule['advanced_id'] ) !== '' ? esc_html( trim( $rule['advanced_id'] ) ) : ''; ?></td>
										<?php } ?>
										<td></td>
									</tr>
									<tr id="tdw-edit-rule-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" class="tdw-hidden tdw-edit-rule tdw-edit-rule-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>">
										<td>
											<input type="number" id="tdw-form-edit-priorit-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-priority" value="<?php echo intval( $priority ); ?>" min="1" max="999" step="1" class="required">
										</td>
										<td>
											<input type="checkbox" id="tdw-form-edit-active-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-active" value="1" <?php checked( $rule['active'], 1 ); ?>>
										</td>
										<td>
											<input type="hidden" id="tdw-form-edit-type-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-type" value="<?php echo esc_attr( $rule['type'] ); ?>">
											<input type="hidden" id="tdw-form-edit-term-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-term" value="<?php echo intval( $term_id ); ?>">
											<div class="row-actions">
												<span class="trash editcancel">
													<a href="#" data-meta-id="<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>"><?php esc_html_e( 'Cancel', 'taxonomy-discounts-woocommerce' ); ?></a>
												</span>
											</div>
										</td>
										<td>
											<?php self::wp_dropdown_roles( 'edit', $user_role ); ?>
										</td>
										<td>
										<?php
										switch ( $rule['type'] ) {
											case 'percentage':
												?>
												<span title="<?php echo esc_attr__( 'Min. Qtt.', 'taxonomy-discounts-woocommerce' ); ?>"><input type="number" id="tdw-form-edit-percentage-min-qtt-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-percentage-min-qtt" min="0" step="1" placeholder="0" value="<?php echo intval( $rule['min-qtt'] ); ?>"></span>
												/
												<span title="<?php echo esc_attr__( 'Discount', 'taxonomy-discounts-woocommerce' ); ?>"><input type="number" id="tdw-form-edit-percentage-value-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-percentage-value" min="1" max="99" step="1" placeholder="0" value="<?php echo intval( $rule['value'] ); ?>" class="required">%</span>
												/
												<span>
													<select id="tdw-form-add-percentage-aggr-var" name="tdw-form-add-percentage-aggr-var">
														<option value="0"
														<?php
														if ( ! $rule['aggr-var'] ) {
															echo ' selected="selected"';}
														?>
														><?php esc_html_e( 'Do not aggr. var.', 'taxonomy-discounts-woocommerce' ); ?></option>
														<option value="1"
														<?php
														if ( $rule['aggr-var'] ) {
															echo ' selected="selected"';}
														?>
														><?php esc_html_e( 'Aggr. var.', 'taxonomy-discounts-woocommerce' ); ?></option>
													</select>
												</span>
												<?php
												break;
											case 'x-for-y':
												?>
												<span><input type="number" id="tdw-form-edit-x-for-y-x-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-x-for-y-x" min="1" step="1" placeholder="x" value="<?php echo intval( $rule['x'] ); ?>" class="required"></span>
												/
												<span><input type="number" id="tdw-form-edit-x-for-y-y-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-x-for-y-y" min="1" step="1" placeholder="y" value="<?php echo intval( $rule['y'] ); ?>" class="required"></span>
												<?php do_action( 'tdw_admin_after_x_for_y_edit_form', $rule ); ?>
												<?php
												break;
											default:
												do_action( 'tdw_admin_non_default_edit_form', $rule );
												break;

										}
										do_action( 'tdw_admin_after_discount_type_edit_form', $rule );
										?>
										</td>
										<td>
											<input type="checkbox" id="tdw-form-edit-disable-coupon-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-disable-coupon" value="1" <?php checked( $rule['disable_coupon'], 1 ); ?>>
										</td>
										<td>
											<input type="text" class="tdw-date-field" id="tdw-form-edit-from-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-from" placeholder="<?php esc_attr_e( 'yyyy-mm-dd', 'taxonomy-discounts-woocommerce' ); ?>" value="<?php echo esc_attr( substr( trim( $rule['from'] ), 0, 10 ) ); ?>" maxlength="10">
											<?php
											if ( $this->enable_time ) {
												?>
												<input type="text" class="tdw-time-field" name="tdw-form-edit-from-time" id="tdw-form-add-from-time-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" placeholder="00:00:00" value="<?php echo esc_attr( substr( trim( $rule['from'] ), 11, 19 ) ); ?>" maxlength="8">
												<?php
											}
											?>
										</td>
										<td>
											<input type="text" class="tdw-date-field" id="tdw-form-edit-to-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-to" placeholder="<?php esc_attr_e( 'yyyy-mm-dd', 'taxonomy-discounts-woocommerce' ); ?>" value="<?php echo esc_attr( substr( trim( $rule['to'] ), 0, 10 ) ); ?>" maxlength="10">
											<?php
											if ( $this->enable_time ) {
												?>
												<input type="text" class="tdw-time-field" name="tdw-form-edit-to-time" id="tdw-form-add-to-time-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" placeholder="23:59:59" value="<?php echo esc_attr( substr( trim( $rule['to'] ), 11, 19 ) ); ?>" maxlength="8">
												<?php
											}
											?>
										</td>
										<?php
										if (
											( defined( 'WCTD_ADVANCED_MODE' ) && WCTD_ADVANCED_MODE )
											||
											apply_filters( 'tdw_dev_mode', false )
										) {
											?>
											<td>
												<input type="text" id="tdw-form-edit-advanced-id-<?php echo esc_html( isset( $rule['meta_id_prefix'] ) ? $rule['meta_id_prefix'] : '' ); ?><?php echo intval( $rule['meta_id'] ); ?>" name="tdw-form-edit-advanced-id" value="<?php echo esc_attr( isset( $rule['advanced_id'] ) ? trim( $rule['advanced_id'] ) : '' ); ?>" maxlength="10">
											</td>
										<?php } ?>
										<td>
											<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save', 'taxonomy-discounts-woocommerce' ); ?>">
										</td>
									</tr>
									<?php
									do_action( 'tdw_admin_discount_rules_table_after_rule', $rule );
								}
							}
						}
					} else {
						?>
						<tr>
							<td colspan="7" style="text-align: center;"><?php esc_html_e( 'No Taxonomy/Term based discounts created yet', 'taxonomy-discounts-woocommerce' ); ?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</form>
		<input type="hidden" id="tdw-last-priority" value="<?php echo intval( $priority ); ?>">
		<input type="hidden" id="tdw-edit-form-id" value="">
		<?php
	}

	/**
	 * AJAX handler for loading terms dropdown when taxonomy is selected
	 *
	 * Generates and outputs the terms dropdown HTML for the selected taxonomy
	 */
	public function ajax_form_add_choose_taxonomy() {
		// Validate nonce
		if ( $this->admin_ajax_secure_calls() ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			?>
				<label for="tdw-form-add-term"><strong><?php esc_html_e( 'Term', 'taxonomy-discounts-woocommerce' ); ?></strong>:</label>
				<br>
				<?php
				$taxonomy = isset( $_POST['taxonomy'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) ) : '';
				if ( taxonomy_exists( $taxonomy ) ) {
					$terms = self::wp_dropdown_categories(
						array(
							'show_option_none'  => '- &nbsp;' . esc_html__( 'choose', 'taxonomy-discounts-woocommerce' ) . '&nbsp; -',
							'option_none_value' => '',
							'show_count'        => true,
							'hide_empty'        => false,
							'hierarchical'      => true,
							'name'              => 'tdw-form-add-term',
							'id'                => 'tdw-form-add-term',
							'taxonomy'          => $taxonomy,
							'hide_if_empty'     => true,
							'echo'              => false,
						)
					);
					// Not escaped because wp_dropdown_categories does not need it (and it fails with wp_kses_post)
					if ( trim( $terms ) !== '' ) {
						echo $terms; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						do_action( 'tdw_admin_after_add_terms_dropdown' );
					} else {
						esc_html_e( 'No terms available', 'taxonomy-discounts-woocommerce' );
					}
				} else {
					?>
					<!-- No taxonomy -->
					<input type="" name="tdw-form-add-term" id="tdw-form-add-term" value="0">
					<?php
					esc_html_e( 'N/A', 'taxonomy-discounts-woocommerce' );
				}
				?>
			<?php
			wp_die();
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * AJAX handler for saving a new discount rule
	 *
	 * Validates and saves a new discount rule to the database
	 */
	public function ajax_form_add_submit() {
		// Validate nonce
		if ( $this->admin_ajax_secure_calls() ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['tdw-form-add-term'] ) ) {
				$data = array();
				$type = isset( $_POST['tdw-form-add-type'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-add-type'] ) ) ) : '';
				switch ( $type ) {
					case 'percentage':
						if ( isset( $_POST['tdw-form-add-percentage-value'] ) && intval( $_POST['tdw-form-add-percentage-value'] ) >= 1 && intval( $_POST['tdw-form-add-percentage-value'] ) <= 99 ) {
							$data['min-qtt']  = isset( $_POST['tdw-form-add-percentage-min-qtt'] ) ? floatval( $_POST['tdw-form-add-percentage-min-qtt'] ) : 0;
							$data['value']    = isset( $_POST['tdw-form-add-percentage-value'] ) ? intval( $_POST['tdw-form-add-percentage-value'] ) : 0;
							$data['aggr-var'] = ( isset( $_POST['tdw-form-add-percentage-aggr-var'] ) && intval( $_POST['tdw-form-add-percentage-aggr-var'] ) === 1 ? true : false );
						}
						break;
					case 'x-for-y':
						if ( isset( $_POST['tdw-form-add-x-for-y-x'] ) && intval( $_POST['tdw-form-add-x-for-y-x'] ) >= 1 && isset( $_POST['tdw-form-add-x-for-y-y'] ) && intval( $_POST['tdw-form-add-x-for-y-y'] ) >= 1 && intval( $_POST['tdw-form-add-x-for-y-x'] ) > intval( $_POST['tdw-form-add-x-for-y-y'] ) ) {
							$data['x'] = intval( $_POST['tdw-form-add-x-for-y-x'] );
							$data['y'] = intval( $_POST['tdw-form-add-x-for-y-y'] );
						}
						break;
					default:
						// PRO integration below on ttdw_form_add_data he filter
						break;
				}
				$data = apply_filters( 'tdw_form_add_data', $data, $_POST ); // Let pro manipulate data
				if ( count( $data ) > 0 ) {
					$data['user_role']      = isset( $_POST['tdw-form-add-role'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-add-role'] ) ) ) : '';
					$data['priority']       = isset( $_POST['tdw-form-add-priority'] ) ? intval( $_POST['tdw-form-add-priority'] ) : 0;
					$data['type']           = isset( $_POST['tdw-form-add-type'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-add-type'] ) ) ) : '';
					$data['active']         = ( isset( $_POST['tdw-form-add-active'] ) && intval( $_POST['tdw-form-add-active'] ) === 1 ? true : false );
					$data['disable_coupon'] = ( isset( $_POST['tdw-form-add-disable-coupon'] ) && intval( $_POST['tdw-form-add-disable-coupon'] ) === 1 ? true : false );
					$data['taxonomy']       = isset( $_POST['tdw-form-add-taxonomy'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-add-taxonomy'] ) ) ) : '';
					$add_from               = isset( $_POST['tdw-form-add-from'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-add-from'] ) ) ) : '';
					$add_from_time          = isset( $_POST['tdw-form-add-from-time'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-add-from-time'] ) ) ) : '';
					$data['from']           = trim( $add_from ) !== '' ? trim( $add_from ) . ' ' . ( $this->enable_time ? ( trim( $add_from_time ) !== '' ? $add_from_time : '00:00:00' ) : '00:00:00' ) : '';
					$add_to                 = isset( $_POST['tdw-form-add-to'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-add-to'] ) ) ) : '';
					$add_to_time            = isset( $_POST['tdw-form-add-to-time'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-add-to-time'] ) ) ) : '';
					$data['to']             = trim( $add_to ) !== '' ? trim( $add_to ) . ' ' . ( $this->enable_time ? ( trim( $add_to_time ) !== '' ? $add_to_time : '23:59:59' ) : '23:59:59' ) : '';
					if ( trim( $data['from'] ) !== '' && trim( $data['to'] ) !== '' && trim( $data['to'] ) < trim( $data['from'] ) ) {
						$temp         = $data['to'];
						$data['to']   = $data['from'];
						$data['from'] = $temp;
					}
					if (
						( defined( 'WCTD_ADVANCED_MODE' ) && WCTD_ADVANCED_MODE )
						||
						apply_filters( 'tdw_dev_mode', false )
					) {
						$data['advanced_id'] = isset( $_POST['tdw-form-advanced-id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-advanced-id'] ) ) ) : '';
					}
					$data = apply_filters( 'tdw_form_add_data_before_save', $data, $_POST ); // Let pro or others manipulate the final data
					// This is where we should check for integer or array of integers and add to several termns at the same time
					// The interface should only be implemented on the PRO Add-on, but the logic needs to be here
					// See: https://github.com/webdados/Taxonomy-Term-and-Role-based-Discounts-for-WooCommerce---PRO-add-on/issues/12
					if ( ( ! is_array( $_POST['tdw-form-add-term'] ) ) && intval( $_POST['tdw-form-add-term'] ) > 0 ) {
						// Default: save to one term
						add_term_meta( intval( $_POST['tdw-form-add-term'] ), $this->discount_rule_meta_key, $data );
					} elseif ( is_array( $_POST['tdw-form-add-term'] ) ) {
						// If an array of terms is sent, we loop through it and add the rule to each term - From PRO Add-on
						foreach ( $_POST['tdw-form-add-term'] as $term_id ) {
							if ( intval( $term_id ) > 0 ) {
								add_term_meta( intval( $term_id ), $this->discount_rule_meta_key, $data );
							}
						}
					}
					do_action( 'tdw_rule_add', intval( $_POST['tdw-form-add-term'] ), $data['taxonomy'], $data );
					echo '1';
				}
			}
			wp_die();
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * AJAX handler for updating an existing discount rule
	 *
	 * Validates and updates an existing discount rule in the database
	 */
	public function ajax_form_edit_submit() {
		// Validate nonce
		if ( $this->admin_ajax_secure_calls() ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['meta_id'] ) && is_numeric( $_POST['meta_id'] ) && intval( $_POST['meta_id'] ) > 0 ) {
				// We should not be using SQL but we might have several rules for the same term_id, so we need to do it by meta_id
				global $wpdb;
				$results  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT * FROM $wpdb->termmeta WHERE meta_id = %d and meta_key = %s",
						intval( $_POST['meta_id'] ),
						$this->discount_rule_meta_key
					)
				);
				$results  = $results[0];
				$old_data = maybe_unserialize( $results->meta_value );
			} else {
				$results  = false;
				$old_data = array(
					'meta_id'  => trim( sanitize_text_field( wp_unslash( $_POST['meta_id'] ) ) ),
					'taxonomy' => isset( $_POST['tdw-form-edit-taxonomy'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-taxonomy'] ) ) ) : '',
				);
			}
			$data = array();
			$type = isset( $_POST['tdw-form-edit-type'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-type'] ) ) ) : '';
			switch ( $type ) {
				case 'percentage':
					$percentage_value = isset( $_POST['tdw-form-edit-percentage-value'] ) ? intval( $_POST['tdw-form-edit-percentage-value'] ) : 0;
					if ( $percentage_value >= 1 && $percentage_value <= 99 ) {
						$percentage_min_qtt = isset( $_POST['tdw-form-edit-percentage-min-qtt'] ) ? floatval( $_POST['tdw-form-edit-percentage-min-qtt'] ) : 0;
						$data['min-qtt']    = $percentage_min_qtt;
						$data['value']      = $percentage_value;
						$data['aggr-var']   = ( isset( $_POST['tdw-form-add-percentage-aggr-var'] ) && intval( $_POST['tdw-form-add-percentage-aggr-var'] ) === 1 ? true : false );
					}
					break;
				case 'x-for-y':
					$x = isset( $_POST['tdw-form-edit-x-for-y-x'] ) ? intval( $_POST['tdw-form-edit-x-for-y-x'] ) : 0;
					$y = isset( $_POST['tdw-form-edit-x-for-y-y'] ) ? intval( $_POST['tdw-form-edit-x-for-y-y'] ) : 0;
					if ( $x >= 1 && $y >= 1 && $x > $y ) {
						$data['x'] = $x;
						$data['y'] = $y;
					}
					break;
				default:
					// PRO integration below on the filter
					break;
			}
			$data = apply_filters( 'tdw_form_edit_data', $data, $_POST ); // Let pro manipulate data
			if ( count( $data ) > 0 ) {
				$data['user_role']      = isset( $_POST['tdw-form-edit-role'] ) && trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-role'] ) ) ) !== '' ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-role'] ) ) ) : '';
				$data['priority']       = isset( $_POST['tdw-form-edit-priority'] ) ? intval( $_POST['tdw-form-edit-priority'] ) : 0;
				$data['type']           = isset( $_POST['tdw-form-edit-type'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-type'] ) ) ) : '';
				$data['active']         = ( isset( $_POST['tdw-form-edit-active'] ) && intval( $_POST['tdw-form-edit-active'] ) === 1 ? true : false );
				$data['disable_coupon'] = ( isset( $_POST['tdw-form-edit-disable-coupon'] ) && intval( $_POST['tdw-form-edit-disable-coupon'] ) === 1 ? true : false );
				$data['taxonomy']       = $old_data['taxonomy'];
				$data['from']           = isset( $_POST['tdw-form-edit-from'] ) && trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-from'] ) ) ) !== '' ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-from'] ) ) ) . ' ' . ( $this->enable_time ? ( isset( $_POST['tdw-form-edit-from-time'] ) && trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-from-time'] ) ) ) !== '' ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-from-time'] ) ) ) : '00:00:00' ) : '00:00:00' ) : '';
				$data['to']             = isset( $_POST['tdw-form-edit-to'] ) && trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-to'] ) ) ) !== '' ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-to'] ) ) ) . ' ' . ( $this->enable_time ? ( isset( $_POST['tdw-form-edit-to-time'] ) && trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-to-time'] ) ) ) !== '' ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-to-time'] ) ) ) : '23:59:59' ) : '23:59:59' ) : '';
				if ( trim( $data['from'] ) !== '' && trim( $data['to'] ) !== '' && trim( $data['to'] ) < trim( $data['from'] ) ) {
					$temp         = $data['to'];
					$data['to']   = $data['from'];
					$data['from'] = $temp;
				}
				if (
					( defined( 'WCTD_ADVANCED_MODE' ) && WCTD_ADVANCED_MODE )
					||
					apply_filters( 'tdw_dev_mode', false )
				) {
					$data['advanced_id'] = isset( $_POST['tdw-form-edit-advanced-id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tdw-form-edit-advanced-id'] ) ) ) : '';
				}
				if ( $results && isset( $_POST['tdw-form-edit-term'] ) && intval( $_POST['tdw-form-edit-term'] ) > 0 && isset( $_POST['meta_id'] ) && intval( $_POST['meta_id'] ) > 0 ) {
					$data = apply_filters( 'tdw_form_edit_data_before_save', $data, $_POST ); // Let pro or others manipulate the final data
					update_term_meta( intval( $_POST['tdw-form-edit-term'] ), $this->discount_rule_meta_key, $data, $old_data );
				}
				do_action( 'tdw_rule_edit', intval( $_POST['tdw-form-edit-term'] ), $data['taxonomy'], $data, $old_data );
				echo '1';
			}
			wp_die();
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * AJAX handler for deleting a discount rule
	 *
	 * Permanently removes a discount rule from the database
	 */
	public function ajax_delete_rule() {
		// Validate nonce
		if ( $this->admin_ajax_secure_calls() ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['meta_id'] ) && is_numeric( $_POST['meta_id'] ) && intval( $_POST['meta_id'] ) > 0 ) {
				$meta_id = isset( $_POST['meta_id'] ) ? intval( $_POST['meta_id'] ) : 0;
				// Should use delete_term_meta - but we might have several rules for the same term_id, so we need to do it by meta_id
				global $wpdb;
				// We need to get details for the action first...
				$meta = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT * FROM $wpdb->termmeta WHERE meta_id = %d and meta_key = %s",
						intval( $_POST['meta_id'] ),
						$this->discount_rule_meta_key
					)
				);
				if ( ! empty( $meta ) ) {
					$meta    = $meta[0];
					$term_id = $meta->term_id;
					$data    = maybe_unserialize( $meta->meta_value );
					// ...and then delete it
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM $wpdb->termmeta WHERE meta_id = %d and meta_key = %s",
							intval( $_POST['meta_id'] ),
							$this->discount_rule_meta_key
						)
					);
					clean_term_cache( $term_id );
				}
			} else {
				$term_id = 0;
				$data    = array(
					'meta_id'  => trim( sanitize_text_field( wp_unslash( $_POST['meta_id'] ) ) ),
					'taxonomy' => isset( $_POST['taxonomy'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) ) : '',
				);
			}
			do_action( 'tdw_rule_delete', $term_id, $data['taxonomy'], $data );
			echo '1';
			wp_die();
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Get all registered product taxonomies
	 *
	 * Retrieves all taxonomy objects associated with the 'product' post type
	 *
	 * @return array Array of taxonomy objects.
	 */
	public function get_product_taxonomies() {
		$taxonomy_objects = get_object_taxonomies( 'product', 'objects' );
		return $taxonomy_objects;
	}

	/**
	 * Add product to the "do not apply discount" cache
	 *
	 * Marks a product as excluded from discount application (used by PRO features like "Exclude on sale")
	 *
	 * @param int $product_id The product ID to exclude from discounts.
	 */
	public function cache_do_not_apply_discount( $product_id ) {
		if ( ! in_array( (int) $product_id, $this->cache_do_not_apply_discount, true ) ) {
			$this->cache_do_not_apply_discount[] = (int) $product_id;
		}
	}
}
