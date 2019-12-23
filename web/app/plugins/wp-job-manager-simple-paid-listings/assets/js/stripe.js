window.onload = function() {
	var cardElement = document.getElementById( 'stripe-card-element' );
	if ( ! cardElement ) {
		return;
	}

	var stripe = Stripe( stripe_checkout_params.key );
	var elements = stripe.elements( {
		locale: stripe_checkout_params.locale
	} );

	// Custom styling can be passed to options when creating an Element.
	var style = {
		base: {
			color: '#32325d',
			fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
			fontSmoothing: 'antialiased',
			fontSize: '16px',
			'::placeholder': {
				color: '#aab7c4'
			}
		},
		invalid: {
			color: '#fa755a',
			iconColor: '#fa755a'
		}
	};

	// Create an instance of the card Element.
	var card = elements.create( 'card', { style: style } );

	card.mount( '#stripe-card-element' );

	// Handle real-time validation errors from the card Element.
	card.addEventListener( 'change', function ( event ) {
		var displayError = document.getElementById( 'stripe-card-errors' );
		if ( event.error ) {
			displayError.textContent = event.error.message;
		} else {
			displayError.textContent = '';
		}
	} );

	// Handle form submission.
	var paymentIncluded = false;
	var form = document.getElementById( 'stripe-checkout-form' );
	var cardholderName = document.getElementById( 'stripe-cardholder-name' );
	form.addEventListener( 'submit', function ( event ) {
		if ( paymentIncluded ) {
			return true;
		}
		event.preventDefault();

		var displayError = document.getElementById('stripe-card-errors');
		var spinner = this.querySelector('.spinner');
		var buttons  = this.querySelectorAll( 'input[type=submit]' );
		buttons.forEach( function( button ) {
			button.classList.add( 'disabled' );
			button.addEventListener( 'click', function( event ) {
				if ( button.classList.contains( 'disabled' ) ) {
					event.preventDefault();

					return false;
				}

				return true;
			} );
		} );

		spinner.classList.add( 'is-active' );

		displayError.textContent = '';

		var clientSecret = form.dataset.secret;
		stripe.handleCardPayment(
			clientSecret,
			card,
			{
				payment_method_data: {
					billing_details: {
						name: cardholderName.value
					}
				}
			}
		).then( function ( result ) {
			if ( result.error ) {
				spinner.classList.remove( 'is-active' );
				buttons.forEach( function( button ) {
					button.classList.remove( 'disabled' );
				} );

				// Display error.message in your UI.
				displayError.textContent = result.error.message;
			} else if( result.paymentIntent && result.paymentIntent.status === 'succeeded' ) {
				paymentIncluded = true;

				// Submit the form.
				form.submit();
			} else {
				displayError.textContent = stripe_checkout_params.unknown_error_message;
			}
		} );
	} );

};
