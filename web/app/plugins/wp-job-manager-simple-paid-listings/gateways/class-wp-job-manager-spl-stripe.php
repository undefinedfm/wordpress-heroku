<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_SPL_Stripe
 */
class WP_Job_Manager_SPL_Stripe extends WP_Job_Manager_SPL_Gateway {
	const META_DATA_KEY_INTENT = '_stripe_intent_id';

	private $api_endpoint = 'https://api.stripe.com/';
	private $intent_cache = array();

	/**
	 * __construct function.
	 */
	public function __construct() {
		$this->gateway_id   = 'stripe';
		$this->gateway_name = __( 'Stripe Checkout', 'wp-job-manager-simple-paid-listings' );
		$this->settings     = array(
			array(
				'name'  => 'job_manager_spl_stripe_secret_key',
				'std'   => '',
				'label' => __( 'Secret Key', 'wp-job-manager-simple-paid-listings' ),
				'desc'  => __( 'Get your API keys from your stripe account.', 'wp-job-manager-simple-paid-listings' ),
				'type'  => 'input',
				'class' => 'gateway-settings gateway-settings-stripe',
			),
			array(
				'name'  => 'job_manager_spl_stripe_publishable_key',
				'std'   => '',
				'label' => __( 'Publishable Key', 'wp-job-manager-simple-paid-listings' ),
				'desc'  => __( 'Get your API keys from your stripe account.', 'wp-job-manager-simple-paid-listings' ),
				'type'  => 'input',
				'class' => 'gateway-settings gateway-settings-stripe',
			),
		);

		parent::__construct();
	}

	/**
	 * Initialize the Stripe gateway. Fired only when Stripe is active gateway and we're on the frontend.
	 */
	public function init_frontend() {
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		add_filter( 'submit_job_steps', array( $this, 'submit_job_steps' ) );
	}

	/**
	 * Change the steps during the submission process
	 *
	 * @param  array $steps
	 * @return array
	 */
	public function submit_job_steps( $steps ) {
		$steps['stripe-payment'] = array(
			'name'     => __( 'Pay for listing', 'wp-job-manager-simple-paid-listings' ),
			'view'     => array( $this, 'checkout' ),
			'before'   => array( $this, 'checkout_handler' ), // Run before as well just to check if we already paid.
			'handler'  => array( $this, 'checkout_handler' ),
			'priority' => 25,
		);

		return $steps;
	}

	/**
	 * Display the checkout form.
	 *
	 * @return bool
	 */
	public function checkout() {
		$form = WP_Job_Manager_Form_Submit_Job::instance();

		$currency        = strtoupper( get_option( 'job_manager_spl_currency' ) );
		$currency_symbol = WP_Job_Manager_Simple_Paid_Listings::get_currency_symbol();
		$item_cost_price = WP_Job_Manager_Simple_Paid_Listings::get_job_listing_cost();

		/**
		 * Change the item cost description used at checkout.
		 *
		 * @since 1.4.0
		 *
		 * @param string $item_cost_description Item cost description to use on checkout page.
		 * @param string $job_id                Job ID for the post.
		 * @param string $currency_symbol       Currency symbol to use.
		 * @param string $currency              Currency short description.
		 * @param string $item_cost_price       Price of the job listing.
		 */
		$item_cost = apply_filters(
			'job_manager_spl_stripe_cost_description',
			// translators: %1$s is the currency symbol; %2$s is the price per listing.
			esc_html( sprintf( __( '%1$s%2$s', 'wp-job-manager-simple-paid-listings' ), $currency_symbol, $item_cost_price ) ),
			$form->get_job_id(),
			$currency_symbol,
			$currency,
			$item_cost_price
		);

		/**
		 * Change the item description used at checkout.
		 *
		 * @since 1.4.0
		 *
		 * @param string $item_description Item description to use on checkout page.
		 * @param string $job_id           Job ID for the post.
		 */
		$item_description = apply_filters(
			'job_manager_spl_stripe_item_description',
			get_the_title( $form->get_job_id() ),
			$form->get_job_id()
		);

		$payment_intent_id = false;
		$payment_intent    = self::get_payment_intent( $form->get_job_id() );
		if ( $payment_intent && ! empty( $payment_intent->client_secret ) ) {
			$payment_intent_id = $payment_intent->client_secret;
		}

		if ( ! $payment_intent_id ) {
			$form = WP_Job_Manager_Form_Submit_Job::instance();
			$form->add_error( esc_html__( 'We are currently unable to accept payments. Please try again later.', 'wp-job-manager-simple-paid-listings' ) );
			$form->show_errors();

			return false;
		}

		get_job_manager_template(
			'stripe-checkout.php',
			array(
				'job_id'               => $form->get_job_id(),
				'step'                 => $form->get_step(),
				'form_name'            => $form->form_name,
				'action'               => $form->get_action(),
				'item_description'     => $item_description,
				'item_cost'            => $item_cost,
				'intent_client_secret' => $payment_intent_id,
			),
			'simple-paid-listings',
			JOB_MANAGER_SPL_PLUGIN_DIR . '/templates/'
		);
	}

	/**
	 * Check to see if payment was successful. If so, continue through process.
	 */
	public function checkout_handler() {
		$form           = WP_Job_Manager_Form_Submit_Job::instance();
		$payment_intent = $this->get_payment_intent( $form->get_job_id() );
		if ( ! $payment_intent || ! $this->check_payment_then_proceed( $payment_intent ) ) {
			if ( ! empty( $_POST['submit_payment'] ) ) {
				$form->add_error( esc_html__( 'An error occurred while processing your payment.', 'wp-job-manager-simple-paid-listings' ) );
			}
		}
	}

	/**
	 * Check if payment has been processed. If so, proceed through steps.
	 *
	 * @param object $payment_intent Payment intent object (as returned from Stripe API).
	 * @return bool
	 */
	public function check_payment_then_proceed( $payment_intent ) {
		if ( empty( $payment_intent ) ) {
			return false;
		}

		$form = WP_Job_Manager_Form_Submit_Job::instance();
		try {
			$is_payment_complete = $this->check_payment_intent_paid( $form->get_job_id(), $payment_intent );
			if ( $is_payment_complete ) {
				$form = WP_Job_Manager_Form_Submit_Job::instance();
				$form->next_step();

				return true;
			}
		} catch ( Exception $e ) {
			$form = WP_Job_Manager_Form_Submit_Job::instance();
			$form->add_error( esc_html( $e->getMessage() ) );
			$form->show_errors();
		}

		return false;
	}

	/**
	 * Check payment intent object and see if payment has been made. Records payment information if it was successful.
	 *
	 * @param int    $job_id         Job ID.
	 * @param object $payment_intent Payment intent object (as returned from Stripe API).
	 *
	 * @return bool True if payment has been made, false otherwise.
	 * @throws Exception When payment intent is not set up as we expect it to be.
	 */
	public function check_payment_intent_paid( $job_id, $payment_intent ) {
		if ( empty( $payment_intent ) || empty( $payment_intent->status ) ) {
			return false;
		}

		if ( 'succeeded' !== $payment_intent->status || empty( $payment_intent->amount_received ) || empty( $payment_intent->currency ) ) {
			return false;
		}

		$amount   = WP_Job_Manager_Simple_Paid_Listings::get_job_listing_cost() * 100;
		$currency = strtolower( get_option( 'job_manager_spl_currency' ) );

		if ( intval( $amount ) !== intval( $payment_intent->amount_received ) || $currency !== $payment_intent->currency ) {
			throw new Exception( __( 'Payment received does not match the cost of the job listing.', 'wp-job-manager-simple-paid-listings' ) );
		}

		if ( empty( $payment_intent->metadata->job_id ) || intval( $payment_intent->metadata->job_id ) !== intval( $job_id ) ) {
			throw new Exception( __( 'Payment has been made but does not match the job listing.', 'wp-job-manager-simple-paid-listings' ) );
		}

		if ( ! in_array( get_post_status( $job_id ), array( 'pending_payment', 'expired' ), true ) ) {
			// The payment is complete but it has already been marked.
			return true;
		}

		$charge_ids = array();
		if ( ! empty( $payment_intent->charges ) && ! empty( $payment_intent->charges->data ) ) {
			foreach ( $payment_intent->charges->data as $charge ) {
				if ( 'succeeded' === $charge->status && $charge->paid ) {
					$charge_ids[] = $charge->id;
				}
			}
		}

		if ( ! empty( $charge_ids ) ) {
			update_post_meta( $job_id, 'Stripe Charge ID', implode( '; ', $charge_ids ) );
		}

		delete_post_meta( $job_id, self::META_DATA_KEY_INTENT );
		$this->payment_complete( $job_id );

		// Notify admin
		if ( get_option( 'job_manager_submission_requires_approval' ) ) {
			$this->send_admin_email( $job_id, sprintf( __( 'Payment has been received in full for Job Listing #%d - this job is ready for admin approval.', 'wp-job-manager-simple-paid-listings' ), $job_id ) );
		} else {
			$this->send_admin_email( $job_id, sprintf( __( 'Payment has been received in full for Job Listing #%d - this job has been automatically approved.', 'wp-job-manager-simple-paid-listings' ), $job_id ) );
		}

		return true;
	}

	/**
	 * Load the frontend assets.
	 *
	 * @access public
	 * @return void
	 */
	public function frontend_scripts() {
		wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
		wp_register_script( 'wp-job-manager-spl-stripe', JOB_MANAGER_SPL_PLUGIN_URL . '/assets/js/stripe.min.js', array( 'jquery', 'stripe' ), JOB_MANAGER_SPL_VERSION, true );
		wp_register_style( 'wp-job-manager-spl-stripe', JOB_MANAGER_SPL_PLUGIN_URL . '/assets/css/stripe.css', array(), JOB_MANAGER_SPL_VERSION );

		wp_localize_script(
			'wp-job-manager-spl-stripe', 'stripe_checkout_params', array(
				'unknown_error_message' => esc_html__( 'An unknown error occurred while processing your payment.', 'wp-job-manager-simple-paid-listings' ),
				'key'                   => get_option( 'job_manager_spl_stripe_publishable_key' ),
				'locale'                => get_locale(),
			)
		);

		$enqueue_assets = true;
		if ( function_exists( 'has_wpjm_shortcode' ) ) {
			if ( ! has_wpjm_shortcode( null, 'submit_job_form' ) ) {
				$enqueue_assets = false;
			}
		}

		if ( $enqueue_assets ) {
			wp_enqueue_script( 'wp-job-manager-spl-stripe' );
			wp_enqueue_style( 'wp-job-manager-spl-stripe' );
		}
	}

	/**
	 * Get the payment intent for the upcoming charge.
	 *
	 * @param int $job_id Job ID.
	 * @return string|bool
	 */
	private function get_payment_intent( $job_id ) {
		$current_payment_intent = get_post_meta( $job_id, self::META_DATA_KEY_INTENT, true );
		$payment_intent         = false;
		if ( $current_payment_intent ) {
			$payment_intent = $this->retrieve_payment_intent( $current_payment_intent );
		}

		if ( ! $payment_intent ) {
			$payment_intent = $this->create_payment_intent( $job_id );
			if ( $payment_intent && ! empty( $payment_intent->id ) ) {
				update_post_meta( $job_id, self::META_DATA_KEY_INTENT, sanitize_text_field( $payment_intent->id ) );
			}
		}

		return $payment_intent;
	}

	/**
	 * Retrieve a payment intent.
	 *
	 * @see https://stripe.com/docs/payments/payment-intents
	 *
	 * @param string $payment_intent_id Payment Intent ID.
	 * @return object|bool
	 */
	private function retrieve_payment_intent( $payment_intent_id ) {
		if ( isset( $this->intent_cache[ $payment_intent_id ] ) ) {
			return $this->intent_cache[ $payment_intent_id ];
		}

		try {
			$stripe_request = array(
				'method' => 'GET',
			);

			$response = $this->stripe_request( $this->api_endpoint . 'v1/payment_intents/' . sanitize_text_field( $payment_intent_id ), $stripe_request );

			if ( empty( $response->id ) || ! empty( $response->error ) ) {
				$this->intent_cache[ $payment_intent_id ] = false;

				return false;
			}
			$this->intent_cache[ $payment_intent_id ] = $response;
		} catch ( Exception $e ) {
			$this->intent_cache[ $payment_intent_id ] = false;
		}

		return $this->intent_cache[ $payment_intent_id ];
	}

	/**
	 * Create the payment intent for the upcoming charge.
	 *
	 * @see https://stripe.com/docs/payments/payment-intents
	 *
	 * @param int $job_id Job ID.
	 * @return object|bool
	 */
	private function create_payment_intent( $job_id ) {
		// Currency amount should be sent in smallest unit.
		$amount   = WP_Job_Manager_Simple_Paid_Listings::get_job_listing_cost() * 100;
		$currency = strtoupper( get_option( 'job_manager_spl_currency' ) );

		// Get email address
		$email = false;

		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$email        = $current_user->user_email;
		} elseif( $job_id) {
			$application = get_post_meta( $job_id, '_application', true );
			if ( ! empty( $application ) && is_email( $application ) ) {
				$email = $application;
			}
		}

		try {
			$stripe_request = array(
				'method' => 'POST',
				'body'   => array(
					'amount'      => $amount,
					'currency'    => $currency,
					// translators: Placeholder is the title of the job listing.
					'description' => html_entity_decode( sprintf( __( 'New Job Listing "%s"', 'wp-job-manager-simple-paid-listings' ),  get_the_title( $job_id ) ) ),
					'metadata'    => array( 'job_id' => intval( $job_id ) ),
				),
			);

			if ( $email ) {
				$stripe_request['body']['receipt_email'] = $email;
			}

			$response = $this->stripe_request( $this->api_endpoint . 'v1/payment_intents', $stripe_request );

			if ( empty( $response->id ) || ! empty( $response->error ) ) {
				throw new Exception( __( 'Invalid response.', 'wp-job-manager-simple-paid-listings' ) );
			}

			$this->intent_cache[ $response->id ] = $response;

			return $response;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Make a request to the Stripe API.
	 *
	 * @param string $url          Request URL.
	 * @param array  $request_args Request arguments to send when making request.
	 * @return object Response from Stripe.
	 * @throws Exception For request errors.
	 */
	private function stripe_request( $url, $request_args ) {
		$default_args = array(
			'method'     => 'POST',
			'headers'    => array(
				'Authorization' => 'Basic ' . base64_encode( get_option( 'job_manager_spl_stripe_secret_key' ) . ':' ),
			),
			'body'       => array(),
			'timeout'    => 60,
			'sslverify'  => false,
			'user-agent' => 'WP_Job_Manager',
		);

		$args     = wp_parse_args( $request_args, $default_args );
		$response = wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( __( 'There was a problem connecting to the gateway.', 'wp-job-manager-simple-paid-listings' ) );
		}

		if ( empty( $response['body'] ) ) {
			throw new Exception( __( 'Empty response.', 'wp-job-manager-simple-paid-listings' ) );
		}

		if ( is_wp_error( $response ) ) {
			throw new Exception( __( 'There was a problem connecting to the gateway.', 'wp-job-manager-simple-paid-listings' ) );
		}

		if ( empty( $response['body'] ) ) {
			throw new Exception( __( 'Empty response.', 'wp-job-manager-simple-paid-listings' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			throw new Exception( $parsed_response->error->message );
		}

		return $parsed_response;
	}
}

return WP_Job_Manager_SPL_Stripe::instance();
