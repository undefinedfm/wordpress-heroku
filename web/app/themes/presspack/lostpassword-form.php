<?php
/*
If you would like to edit this file, copy it to your current theme's directory and edit it there.
Theme My Login will always look in your theme's directory first, before using this default template.
*/
?>
<div class="tml tml-lostpassword" id="theme-my-login<?php $template->the_instance(); ?>">
	<?php $template->the_action_template_message( 'lostpassword' ); ?>
	<?php $template->the_errors(); ?>
	<form name="lostpasswordform" id="lostpasswordform<?php $template->the_instance(); ?>" action="<?php $template->the_action_url( 'lostpassword', 'login_post' ); ?>" method="post">
		<div class="sl-user-login-wrap">
			<label class="form-label" for="user_login<?php $template->the_instance(); ?>"><?php _e( 'Username or E-mail:', 'theme-my-login' ); ?></label>
			<input class="text-input" type="text" name="user_login" id="user_login<?php $template->the_instance(); ?>" class="input" value="<?php $template->the_posted_value( 'user_login' ); ?>" size="20" />
		</div>

		<?php do_action( 'lostpassword_form' ); ?>

		<div class="sl-submit-wrap clearfix">
			<input class="button button--secondary xs-float-right" type="submit" name="wp-submit" id="wp-submit<?php $template->the_instance(); ?>" value="<?php esc_attr_e( 'Send Password', 'theme-my-login' ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php $template->the_redirect_url( 'lostpassword' ); ?>" />
			<input type="hidden" name="instance" value="<?php $template->the_instance(); ?>" />
			<input type="hidden" name="action" value="lostpassword" />
		</div>
	</form>
	<?php $template->the_action_links( array( 'lostpassword' => false ) ); ?>
</div>
