<?php
/**
 * Simple stripe checkout form.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-pagination.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager-simple-paid-listings
 * @category    Template
 * @version     1.4.0
 * @since       1.4.0
 */
?>

<form action="<?php echo esc_url( $action ); ?>" method="post" id="stripe-checkout-form" class="job-manager-form" data-secret="<?php echo esc_attr( $intent_client_secret ); ?>">
	<div class="job_listing_preview_title job_listing_stripe_checkout_title">
		<h2><?php esc_html_e( 'Pay for Listing', 'wp-job-manager-simple-paid-listings' ); ?></h2>
	</div>

	<div class="job_listing_stripe_checkout_form">
		<fieldset class="fieldset-stripe_payment fieldset-type-text">
			<label>
				<?php esc_html_e( 'Item', 'wp-job-manager-simple-paid-listings' ); ?>
			</label>

			<div class="field">
				<div class="item-description"><?php echo esc_html( $item_description ); ?></div>
				<div class="item-cost"><?php echo esc_html( $item_cost ); ?></div>
			</div>
		</fieldset>
		<fieldset class="fieldset-name fieldset-type-text">
			<label for="stripe-cardholder-name">
				<?php esc_html_e( 'Cardholder Name', 'wp-job-manager-simple-paid-listings' ); ?>
			</label>
			<div class="field">
				<div class="stripe-name-field">
					<input type="text" class="input-text" name="cardholder-name" id="stripe-cardholder-name" required />
				</div>
			</div>
		</fieldset>
		<fieldset class="fieldset-stripe_payment fieldset-type-text">
			<label for="card-element">
				<?php esc_html_e( 'Payment Details', 'wp-job-manager-simple-paid-listings' ); ?>
			</label>
			<div class="field">
				<div id="stripe-card-element" class="stripe-payment-card-field"></div>
				<div id="stripe-card-errors" class="stripe-payment-card-errors" role="alert"></div>
			</div>
		</fieldset>
	</div>

	<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>" />
	<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>" />
	<input type="hidden" name="job_manager_form" value="<?php echo esc_attr( $form_name ); ?>" />

	<input type="submit" name="submit_payment" class="button" value="<?php esc_attr_e( 'Submit Payment', 'wp-job-manager-simple-paid-listings' ); ?>" />
	<span class="spinner" style="background-image: url(<?php echo esc_url( includes_url( 'images/spinner.gif' ) ); ?>);"></span>

</form>

