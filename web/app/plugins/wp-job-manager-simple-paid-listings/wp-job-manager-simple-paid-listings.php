<?php
/**
 * Plugin Name: WP Job Manager - Simple Paid Listings
 * Plugin URI: https://wpjobmanager.com/add-ons/simple-paid-listings/
 * Description: Add paid listing functionality. Set a price per listing and take payment via Stripe or PayPal before the listing becomes published.
 * Version: 1.4.1
 * Author: Automattic
 * Author URI: https://wpjobmanager.com
 * Requires at least: 4.9
 * Requires PHP: 5.6
 * Tested up to: 5.2
 *
 * WPJM-Product: wp-job-manager-simple-paid-listings
 *
 * Copyright: 2019 Automattic
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Simple_Paid_Listings class.
 */
class WP_Job_Manager_Simple_Paid_Listings {
	const JOB_MANAGER_CORE_MIN_VERSION = '1.33.1';

	private $job_id  = '';

	/**
	 * Gateway object currently in use.
	 *
	 * @var WP_Job_Manager_SPL_Gateway
	 */
	private $gateway;

	/**
	 * Singleton instance of class.
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * __construct function.
	 */
	public function __construct() {
		if ( defined( 'JOB_MANAGER_SPL_PLUGIN_URL' ) ) {
			return;
		}
		define( 'JOB_MANAGER_SPL_VERSION', '1.4.1' );
		define( 'JOB_MANAGER_SPL_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'JOB_MANAGER_SPL_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		// Set up startup actions
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ), 12 );
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ), 13 );
		add_action( 'admin_notices', array( $this, 'version_check' ) );
	}

	/**
	 * Get the single class instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initializes plugin.
	 */
	public function init_plugin() {
		if ( ! class_exists( 'WP_Job_Manager' ) ) {
			return;
		}

		$this->include_gateways();

		$this->job_id = ! empty( $_REQUEST['job_id'] ) ? absint( $_REQUEST['job_id'] ) : 0;

		add_action( 'init', array( $this, 'register_post_status' ), 12 );
		add_filter( 'the_job_status', array( $this, 'the_job_status' ), 10, 2 );
		add_filter( 'job_manager_valid_submit_job_statuses', array( $this, 'valid_submit_job_statuses' ) );
		add_filter( 'job_manager_settings', array( $this, 'settings' ) );

		if ( ! self::is_active() ) {
			return;
		}

		// Add hooks that are active when a price is set and valid gateway is in use.
		add_filter( 'submit_job_steps', array( $this, 'submit_job_steps' ), 10 );
		add_filter( 'submit_job_step_preview_submit_text', array( $this, 'submit_button_text' ), 10 );

		add_action( 'job_manager_job_submitted_content_pending_payment', array( $this, 'job_submitted' ), 10 );
		add_action( 'job_manager_job_submitted_content_expired', array( $this, 'job_submitted' ), 10 );
		add_action( 'job_manager_api_' . strtolower( esc_attr( get_class( $this ) ) ), array( $this, 'api_handler' ) );
		add_filter( 'job_manager_get_dashboard_jobs_args', array( $this, 'dashboard_job_args' ) );
		add_filter( 'job_manager_my_job_actions', array( $this, 'my_job_actions' ), 10, 2 );
		add_action( 'job_manager_my_job_do_action', array( $this, 'my_job_do_action' ), 10, 2 );
		add_filter( 'job_manager_job_is_editable', array( $this, 'prevent_editing_job_pending_payment' ), 10, 2 );

		if ( ! is_admin() ) {
			add_action( 'plugins_loaded', array( $this->get_active_gateway(), 'init_frontend' ), 100 );
		}
	}

	/**
	 * Checks WPJM core version.
	 */
	public function version_check() {
		if ( ! class_exists( 'WP_Job_Manager' ) || ! defined( 'JOB_MANAGER_VERSION' ) ) {
			$screen = get_current_screen();
			if ( null !== $screen && 'plugins' === $screen->id ) {
				$this->display_error( __( '<em>WP Job Manager - Simple Paid Listings</em> requires WP Job Manager to be installed and activated.', 'wp-job-manager-simple-paid-listings' ) );
			}
		} elseif (
			/**
			 * Filters if WPJM core's version should be checked.
			 *
			 * @since 1.3.0
			 *
			 * @param bool   $do_check                       True if the add-on should do a core version check.
			 * @param string $minimum_required_core_version  Minimum version the plugin is reporting it requires.
			 */
			apply_filters( 'job_manager_addon_core_version_check', true, self::JOB_MANAGER_CORE_MIN_VERSION )
			&& version_compare( JOB_MANAGER_VERSION, self::JOB_MANAGER_CORE_MIN_VERSION, '<' )
		) {
			$this->display_error( sprintf( __( '<em>WP Job Manager - Simple Paid Listings</em> requires WP Job Manager %s (you are using %s).', 'wp-job-manager-simple-paid-listings' ), self::JOB_MANAGER_CORE_MIN_VERSION, JOB_MANAGER_VERSION ) );
		}
	}

	/**
	 * Check if the plugin is set up and ready to charge for job listings.
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( self::get_job_listing_cost() <= 0 ) {
			return false;
		}

		$instance = self::instance();
		if ( ! $instance->get_active_gateway() ) {
			return false;
		}

		return true;
	}

	/**
	 * Display error message notice in the admin.
	 *
	 * @param string $message
	 */
	private function display_error( $message ) {
		echo '<div class="error">';
		echo '<p>' . $message . '</p>';
		echo '</div>';
	}

	/**
	 * Get cost of the job listing.
	 *
	 * @return float
	 */
	public static function get_job_listing_cost() {
		$job_listing_cost = 0;
		$job_listing_cost_str = get_option( 'job_manager_spl_listing_cost', 0 );
		if ( ! empty( $job_listing_cost_str ) ) {
			$job_listing_cost = (float) $job_listing_cost_str;
		}

		return apply_filters( 'wp_job_manager_spl_get_job_listing_cost', number_format( $job_listing_cost, 2, '.', '' ) );
	}

	/**
	 * Get currency symbol for the current currency.
	 *
	 * Based on WooCommerce's `get_woocommerce_currency_symbol()`
	 *
	 * @param string $currency Currency to retrieve symbol for. (default: '').
	 * @return string
	 */
	public static function get_currency_symbol( $currency = '' ) {
		if ( ! $currency ) {
			$currency = strtoupper( get_option( 'job_manager_spl_currency' ) );
		}

		$symbols  = array(
			'AED' => '&#x62f;.&#x625;',
			'AFN' => '&#x60b;',
			'ALL' => 'L',
			'AMD' => 'AMD',
			'ANG' => '&fnof;',
			'AOA' => 'Kz',
			'ARS' => '&#36;',
			'AUD' => '&#36;',
			'AWG' => 'Afl.',
			'AZN' => 'AZN',
			'BAM' => 'KM',
			'BBD' => '&#36;',
			'BDT' => '&#2547;&nbsp;',
			'BGN' => '&#1083;&#1074;.',
			'BHD' => '.&#x62f;.&#x628;',
			'BIF' => 'Fr',
			'BMD' => '&#36;',
			'BND' => '&#36;',
			'BOB' => 'Bs.',
			'BRL' => '&#82;&#36;',
			'BSD' => '&#36;',
			'BTC' => '&#3647;',
			'BTN' => 'Nu.',
			'BWP' => 'P',
			'BYR' => 'Br',
			'BYN' => 'Br',
			'BZD' => '&#36;',
			'CAD' => '&#36;',
			'CDF' => 'Fr',
			'CHF' => '&#67;&#72;&#70;',
			'CLP' => '&#36;',
			'CNY' => '&yen;',
			'COP' => '&#36;',
			'CRC' => '&#x20a1;',
			'CUC' => '&#36;',
			'CUP' => '&#36;',
			'CVE' => '&#36;',
			'CZK' => '&#75;&#269;',
			'DJF' => 'Fr',
			'DKK' => 'DKK',
			'DOP' => 'RD&#36;',
			'DZD' => '&#x62f;.&#x62c;',
			'EGP' => 'EGP',
			'ERN' => 'Nfk',
			'ETB' => 'Br',
			'EUR' => '&euro;',
			'FJD' => '&#36;',
			'FKP' => '&pound;',
			'GBP' => '&pound;',
			'GEL' => '&#x20be;',
			'GGP' => '&pound;',
			'GHS' => '&#x20b5;',
			'GIP' => '&pound;',
			'GMD' => 'D',
			'GNF' => 'Fr',
			'GTQ' => 'Q',
			'GYD' => '&#36;',
			'HKD' => '&#36;',
			'HNL' => 'L',
			'HRK' => 'kn',
			'HTG' => 'G',
			'HUF' => '&#70;&#116;',
			'IDR' => 'Rp',
			'ILS' => '&#8362;',
			'IMP' => '&pound;',
			'INR' => '&#8377;',
			'IQD' => '&#x639;.&#x62f;',
			'IRR' => '&#xfdfc;',
			'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
			'ISK' => 'kr.',
			'JEP' => '&pound;',
			'JMD' => '&#36;',
			'JOD' => '&#x62f;.&#x627;',
			'JPY' => '&yen;',
			'KES' => 'KSh',
			'KGS' => '&#x441;&#x43e;&#x43c;',
			'KHR' => '&#x17db;',
			'KMF' => 'Fr',
			'KPW' => '&#x20a9;',
			'KRW' => '&#8361;',
			'KWD' => '&#x62f;.&#x643;',
			'KYD' => '&#36;',
			'KZT' => 'KZT',
			'LAK' => '&#8365;',
			'LBP' => '&#x644;.&#x644;',
			'LKR' => '&#xdbb;&#xdd4;',
			'LRD' => '&#36;',
			'LSL' => 'L',
			'LYD' => '&#x644;.&#x62f;',
			'MAD' => '&#x62f;.&#x645;.',
			'MDL' => 'MDL',
			'MGA' => 'Ar',
			'MKD' => '&#x434;&#x435;&#x43d;',
			'MMK' => 'Ks',
			'MNT' => '&#x20ae;',
			'MOP' => 'P',
			'MRO' => 'UM',
			'MUR' => '&#x20a8;',
			'MVR' => '.&#x783;',
			'MWK' => 'MK',
			'MXN' => '&#36;',
			'MYR' => '&#82;&#77;',
			'MZN' => 'MT',
			'NAD' => '&#36;',
			'NGN' => '&#8358;',
			'NIO' => 'C&#36;',
			'NOK' => '&#107;&#114;',
			'NPR' => '&#8360;',
			'NZD' => '&#36;',
			'OMR' => '&#x631;.&#x639;.',
			'PAB' => 'B/.',
			'PEN' => 'S/',
			'PGK' => 'K',
			'PHP' => '&#8369;',
			'PKR' => '&#8360;',
			'PLN' => '&#122;&#322;',
			'PRB' => '&#x440;.',
			'PYG' => '&#8370;',
			'QAR' => '&#x631;.&#x642;',
			'RMB' => '&yen;',
			'RON' => 'lei',
			'RSD' => '&#x434;&#x438;&#x43d;.',
			'RUB' => '&#8381;',
			'RWF' => 'Fr',
			'SAR' => '&#x631;.&#x633;',
			'SBD' => '&#36;',
			'SCR' => '&#x20a8;',
			'SDG' => '&#x62c;.&#x633;.',
			'SEK' => '&#107;&#114;',
			'SGD' => '&#36;',
			'SHP' => '&pound;',
			'SLL' => 'Le',
			'SOS' => 'Sh',
			'SRD' => '&#36;',
			'SSP' => '&pound;',
			'STD' => 'Db',
			'SYP' => '&#x644;.&#x633;',
			'SZL' => 'L',
			'THB' => '&#3647;',
			'TJS' => '&#x405;&#x41c;',
			'TMT' => 'm',
			'TND' => '&#x62f;.&#x62a;',
			'TOP' => 'T&#36;',
			'TRY' => '&#8378;',
			'TTD' => '&#36;',
			'TWD' => '&#78;&#84;&#36;',
			'TZS' => 'Sh',
			'UAH' => '&#8372;',
			'UGX' => 'UGX',
			'USD' => '&#36;',
			'UYU' => '&#36;',
			'UZS' => 'UZS',
			'VEF' => 'Bs F',
			'VES' => 'Bs.S',
			'VND' => '&#8363;',
			'VUV' => 'Vt',
			'WST' => 'T',
			'XAF' => 'CFA',
			'XCD' => '&#36;',
			'XOF' => 'CFA',
			'XPF' => 'Fr',
			'YER' => '&#xfdfc;',
			'ZAR' => '&#82;',
			'ZMW' => 'ZK',
		);

		$currency_symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '';

		return apply_filters( 'job_manager_spl_currency_symbol', $currency_symbol, $currency );
	}

	/**
	 * Filter job status name
	 *
	 * @param  string $nice_status
	 * @param  string $status
	 * @return string
	 */
	public function the_job_status( $status, $job ) {
		if ( $job->post_status == 'pending_payment' ) {
			$status = __( 'Pending Payment', 'wp-job-manager-simple-paid-listings' );
		}
		return $status;
	}

	/**
	 * Ensure the submit form lets us continue to edit/process a job with the pending_payment status
	 *
	 * @return array
	 */
	public function valid_submit_job_statuses( $status ) {
		$status[] = 'pending_payment';
		return $status;
	}

	/**
	 * Change the steps during the submission process
	 *
	 * @param  array $steps
	 * @return array
	 */
	public function submit_job_steps( $steps ) {
		// We need to hijack the preview submission so we can take a payment.
		$steps['preview']['handler'] = array( $this, 'preview_handler' );

		return $steps;
	}

	/**
	 * Unused function.
	 *
	 * @deprecated 1.4.0
	 */
	public function payment_result_page() {
		_deprecated_function( __METHOD__, '1.4.0' );
	}

	/**
	 * Handle the form when the preview page is submitted
	 */
	public function preview_handler() {
		if ( ! $_POST ) {
			return;
		}

		$form = WP_Job_Manager_Form_Submit_Job::instance();

		// Edit = show submit form again
		if ( ! empty( $_POST['edit_job'] ) ) {
			$form->previous_step();
		}

		// Continue = Take Payment
		if ( ! empty( $_POST['continue'] ) ) {

			$job = get_post( $this->job_id );

			if ( 'preview' === $job->post_status ) {
				$update_job                = array();
				$update_job['ID']          = $job->ID;
				$update_job['post_status'] = 'pending_payment';
				wp_update_post( $update_job );
			}

			/**
			 * Fires when we're handling the preview step.
			 *
			 * @since 1.4.0
			 *
			 * @param int $job_id Job ID for the job being submitted.
			 */
			do_action( 'wp_job_manager_spl_handle_preview_step', $this->job_id );

			/**
			 * Whether we should continue past preview step.
			 *
			 * @since 1.4.0
			 *
			 * @param bool $preview_complete Whether we should continue past the preview step (default: true).
			 * @param int  $job_id           Job ID for the job being submitted.
			 */
			if ( apply_filters( 'wp_job_manager_spl_preview_complete', true, $this->job_id ) ) {
				// If pay for listing returns true we can proceed, otherwise stay in preview mode.
				$form->next_step();
			}
		}
	}

	/**
	 * Change submit button text
	 *
	 * @return string
	 */
	public function submit_button_text( $button_text ) {
		return __( 'Pay for listing &rarr;', 'wp-job-manager-simple-paid-listings' );
	}

	/**
	 * Show a message if pending payment when the done step is reached
	 */
	public function job_submitted( $job ) {
		$gateway = $this->get_active_gateway();
		if ( $gateway ) {
			$gateway->return_handler();
		}

		printf( __( 'Thanks. Your Job listing was submitted successfully and will be visible once payment is verified.', 'wp-job-manager-simple-paid-listings' ), get_permalink( $job->ID ) );
	}

	/**
	 * API Handler
	 *
	 * @return [type]
	 */
	function api_handler() {
		if ( ! empty( $_GET['gateway'] ) ) {
			$gateway = $this->get_gateway( sanitize_text_field( $_GET['gateway'] ) );
			$gateway->api_handler();
		}
	}

	/**
	 * Localisation
	 *
	 * @access private
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'wp-job-manager-simple-paid-listings' );
		load_textdomain( 'wp-job-manager-simple-paid-listings', WP_LANG_DIR . "/wp-job-manager-simple-paid-listings/wp-job-manager-simple-paid-listings-$locale.mo" );

		load_plugin_textdomain( 'wp-job-manager-simple-paid-listings', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Registers new post status.
	 */
	public function register_post_status() {
		global $job_manager;

		register_post_status( 'pending_payment', array(
			'label'                     => _x( 'Pending Payment', 'job_listing', 'wp-job-manager-simple-paid-listings' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Pending Payment <span class="count">(%s)</span>', 'Pending Payment <span class="count">(%s)</span>', 'wp-job-manager-simple-paid-listings' ),
		) );

		add_action( 'pending_payment_to_publish', array( $job_manager->post_types, 'set_expirey' ) );
	}

	/**
	 * Get configured gateway.
	 *
	 * @param string $gateway
	 * @return WP_Job_Manager_SPL_Gateway|bool Returns gateway object or false if invalid.
	 */
	public function get_gateway( $gateway = '' ) {
		if ( '' === $gateway ) {
			_deprecated_argument( __METHOD__, 'Gateway argument is now required field. If you want active gateway, call `get_active_gateway` in this class', '1.4.0' );

			return $this->get_active_gateway();
		}

		$gateway_class = apply_filters( 'wp_job_manager_spl_gateway_class', 'WP_Job_Manager_SPL_' . ucfirst( $gateway ) );
		if ( ! class_exists( $gateway_class ) || ! is_a( $gateway_class, 'WP_Job_Manager_SPL_Gateway' ) ) {
			//return false;
		}

		return $gateway_class::instance();
	}

	/**
	 * Get the active gateway.
	 *
	 * @return WP_Job_Manager_SPL_Gateway|bool
	 */
	public function get_active_gateway() {
		if ( ! isset( $this->gateway ) ) {
			$this->gateway = false;
			$gateway       = get_option( 'job_manager_spl_gateway', 'paypal' );

			if ( ! empty( $gateway ) ) {
				$this->gateway = $this->get_gateway( $gateway );
			}
		}

		return $this->gateway;
	}

	/**
	 * Include gateways
	 */
	public function include_gateways() {
		include_once __DIR__ . '/gateways/abstract-class-wp-job-manager-spl-gateway.php';
		include_once __DIR__ . '/gateways/class-wp-job-manager-spl-paypal.php';
		include_once __DIR__ . '/gateways/class-wp-job-manager-spl-stripe.php';

		/**
		 * Include new gateway files.
		 *
		 * @since 1.4.0
		 */
		do_action( 'wp_job_manager_spl_include_gateways' );
	}

	/**
	 * Add Settings
	 *
	 * @param  array $settings
	 * @return array
	 */
	public function settings( $settings = array() ) {
		add_action( 'admin_footer', array( $this, 'settings_js' ) );

		$settings['paid_listings'] = array(
			__( 'Paid Listings', 'wp-job-manager-simple-paid-listings' ),
			apply_filters(
				'wp_job_manager_spl_settings',
				array(
					array(
						'name' 		=> 'job_manager_spl_listing_cost',
						'std' 		=> '5.00',
						'label' 	=> __( 'Listing Cost', 'wp-job-manager-simple-paid-listings' ),
						'desc'		=> __( 'Enter the cost of new listings, excluding any currency symbols. E.g. <code>9.99</code>', 'wp-job-manager-simple-paid-listings' ),
						'type'      => 'input',
					),
					array(
						'name' 		=> 'job_manager_spl_currency',
						'std' 		=> 'USD',
						'label' 	=> __( 'Currency Code', 'wp-job-manager-simple-paid-listings' ),
						'desc'		=> __( 'Enter the currency code you wish to use. E.g. for US dollars enter <code>USD</code>. Your gateway must support your input currency for payments to work.', 'wp-job-manager-simple-paid-listings' ),
						'type'      => 'input',
					),
					array(
						'name' 		=> 'job_manager_spl_gateway',
						'std' 		=> 'paypal',
						'label' 	=> __( 'Payment Gateway', 'wp-job-manager-simple-paid-listings' ),
						'desc'		=> __( 'Choose the gateway to use for paid listings. If using Stripe you should ensure your Submit Job page is served over HTTPS. You can use <a href="http://wordpress.org/plugins/wordpress-https/">WordPress HTTPS</a> to do this.', 'wp-job-manager-simple-paid-listings' ),
						'options'   => apply_filters( 'wp_job_manager_spl_gateways', array() ),
						'type'      => 'select',
					),
				)
			),
		);

		return $settings;
	}

	/**
	 * After settings
	 */
	public function settings_js() {
		?>
		<script type="text/javascript">
			jQuery('select#setting-job_manager_spl_gateway').change(function() {
				jQuery(this).closest('form').find( 'tr.gateway-settings' ).hide();
				jQuery(this).closest('form').find( 'tr.gateway-settings-' + jQuery(this).val() ).show();
			}).change();
		</script>
		<?php
	}

	/**
	 * Change what jobs are shown on dashboard
	 *
	 * @param  array $args
	 * @return array
	 */
	public function dashboard_job_args( $args = array() ) {
		$args['post_status'][] = 'pending_payment';

		return $args;
	}

	/**
	 * [my_job_actions description]
	 *
	 * @param  array  $actions
	 * @param  object $job
	 * @return array
	 */
	public function my_job_actions( $actions, $job ) {
		if ( 'pending_payment' === $job->post_status && get_option( 'job_manager_submit_job_form_page_id' ) ) {
			$actions['pay'] = array(
				'label' => __( 'Pay', 'wp-job-manager-simple-paid-listings' ),
				'nonce' => true,
			);
		}

		return $actions;
	}

	/**
	 * Do pay action
	 *
	 * @param  string  $action
	 * @param  integer $job_id
	 * @return [type]
	 */
	public function my_job_do_action( $action = '', $job_id = 0 ) {
		$submit_page_url = get_permalink( get_option( 'job_manager_submit_job_form_page_id' ) );
		if (
			$submit_page_url
			&& 'pay' === $action
			&& $job_id
		) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'step'   => 'preview',
						'job_id' => absint( $job_id ),
					),
					$submit_page_url
				)
			);
			exit;
		}
	}

	/**
	 * Don't allow jobs that have pending payment to be edited.
	 *
	 * @param bool $job_is_editable If the job is editable.
	 * @param int  $job_id          Job ID to check.
	 * @return bool
	 */
	public function prevent_editing_job_pending_payment( $job_is_editable, $job_id ) {
		$job = get_post( $job_id );
		if (
			$job instanceof WP_Post
			&& 'job_listing' === $job->post_type
			&& 'pending_payment' === $job->post_status
		) {
			return false;
		}

		return $job_is_editable;
	}
}

$GLOBALS['job_manager_simple_paid_listings'] = WP_Job_Manager_Simple_Paid_Listings::instance();
