<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_SPL_Gateway class.
 */
abstract class WP_Job_Manager_SPL_Gateway {

	protected $settings     = array();
	protected $gateway_id   = '';
	protected $gateway_name = '';

	private static $instances = array();

	/**
	 * Return the instance of the called class.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( ! isset( self::$instances[ static::class ] ) ) {
			self::$instances[ static::class ] = new static();
		}

		return self::$instances[ static::class ];
	}

	/**
	 * __construct function.
	 */
	public function __construct() {
		add_filter( 'wp_job_manager_spl_gateways', array( $this, 'add_gateway' ) );
		add_filter( 'wp_job_manager_spl_settings', array( $this, 'add_settings' ) );

		if ( ! in_array( static::class, array( 'WP_Job_Manager_SPL_PayPal', 'WP_Job_Manager_SPL_Stripe' ), true ) ) {
			// For backwards compatibility with custom payment gateways, add a filter for the preview handler.
			add_filter( 'wp_job_manager_spl_preview_complete', array( $this, 'legacy_preview_complete_handler' ), 10, 2 );
		}
	}

	/**
	 * Whether we should continue past preview step.
	 *
	 * @param bool $preview_complete Whether we should continue past the preview step (default: true).
	 * @param int  $job_id           Job ID for the job being submitted.
	 * @return bool
	 */
	public function legacy_preview_complete_handler( $preview_complete, $job_id ) {
		if ( ! $this->pay_for_listing( $job_id ) ) {
			return false;
		}

		return $preview_complete;
	}

	/**
	 * Add gateway to settings page.
	 *
	 * @param array $gateways
	 * @return array
	 */
	public function add_gateway( $gateways ) {
		$gateways[ $this->gateway_id ] = $this->gateway_name;

		return $gateways;
	}

	/**
	 * Add settings for the gateway
	 *
	 * @param array $settings
	 */
	public function add_settings( $settings ) {
		return array_merge( $settings, $this->settings );
	}

	/**
	 * Initialize frontend hooks.
	 */
	public function init_frontend() {}

	/**
	 * Handle API calls (optional - used for IPN)
	 */
	public function api_handler() {}

	/**
	 * Handle the return page (optional - used for getting return values from gateways if posted)
	 */
	public function return_handler() {}

	/**
	 * Pay for a job listing action
	 */
	public function pay_for_listing( $job_id ) {
		return false;
	}

	/**
	 * Payment is complete - update listing
	 *
	 * @param  int $job_id
	 */
	public function payment_complete( $job_id ) {
		$job = get_post( $job_id );

		if ( in_array( $job->post_status, array( 'pending_payment', 'expired' ) ) ) {
			// Reset expirey
			delete_post_meta( $job_id, '_job_expires' );

			// Update status
			$update_job                  = array();
			$update_job['ID']            = $job_id;
			$update_job['post_status']   = get_option( 'job_manager_submission_requires_approval' ) ? 'pending' : 'publish';
			$update_job['post_date']     = current_time( 'mysql' );
			$update_job['post_date_gmt'] = current_time( 'mysql', 1 );
			wp_update_post( $update_job );
		}

		do_action( 'wp_job_manager_spl_payment_complete', $job_id );
	}

	/**
	 * Send a message to admin about payment
	 *
	 * @param  int    $job_id
	 * @param  string $message
	 */
	public function send_admin_email( $job_id, $message ) {
		$message = "Hi,\n\n" . $message . "\n\nView this job: " . admin_url( 'post.php?post=' . $job_id . '&action=edit' );

		wp_mail( apply_filters( 'wp_job_manager_spl_admin_email', get_option( 'admin_email' ) ), sprintf( __( 'Job #%d Payment Update', 'wp-job-manager-simple-paid-listings' ), $job_id ), $message );
	}

	/**
	 * Gets the email address for the author of a job listing.
	 *
	 * @param int $job_id
	 *
	 * @return bool|string False if not found, otherwise returns email address.
	 */
	public function get_job_listing_author_email( $job_id ) {
		if ( ( $job = get_post( $job_id ) )
			 && ( $author_id = $job->post_author )
			 && ( $author = get_user_by( 'id', $author_id ) )
			 && ! empty( $author->user_email )
		) {
			return $author->user_email;
		}
		return false;
	}
}
