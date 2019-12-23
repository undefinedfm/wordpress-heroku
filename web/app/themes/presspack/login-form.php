<?php
/*
If you would like to edit this file, copy it to your current theme's directory and edit it there.
Theme My Login will always look in your theme's directory first, before using this default template.
*/
?>
<div class="mx-auto max-w-xs" id="theme-my-login<?php $template->the_instance(); ?>">
	<?php $template->the_action_template_message('login'); ?>
	<?php $template->the_errors(); ?>
	<form name="loginform" id="loginform<?php $template->the_instance(); ?>" action="<?php $template->the_action_url('login', 'login_post'); ?>" method="post">
		<p class="tml-user-login-wrap mb-4">
			<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="user_login<?php $template->the_instance(); ?>">
				<?php
				if ('username' == $theme_my_login->get_option('login_type')) {
					_e('Username', 'theme-my-login');
				} elseif ('email' == $theme_my_login->get_option('login_type')) {
					_e('E-mail', 'theme-my-login');
				} else {
					_e('Username or E-mail', 'theme-my-login');
				}
				?></label>
			<input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" type="text" name="log" id="user_login<?php $template->the_instance(); ?>" value="<?php $template->the_posted_value('log'); ?>" size="20" />
		</p>

		<p class="tml-user-pass-wrap mb-6">
			<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="user_pass<?php $template->the_instance(); ?>"><?php _e('Password', 'theme-my-login'); ?></label>
			<input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" type="password" name="pwd" id="user_pass<?php $template->the_instance(); ?>" placeholder="******************" value="" size="20" autocomplete="off" />
		</p>

		<?php do_action('login_form'); ?>

		<div class="flex items-center justify-between">

			<!-- <label class=" block text-gray-500 font-bold" for="rememberme<?php $template->the_instance(); ?>">
				<input class="mr-2 leading-tight" name="rememberme" type="checkbox" id="rememberme<?php $template->the_instance(); ?>" value="forever">
				<span class="text-sm">
					<?php esc_attr_e('Remember Me', 'theme-my-login'); ?>
				</span>
			</label> -->


			<div class="flex-1">
				<input class="w-full text-lg shadow text-center bg-blue-500 hover:bg-blue-400 focus:shadow-outline focus:outline-none text-white font-bold py-3 px-4 rounded" type="submit" name="wp-submit" id="wp-submit<?php $template->the_instance(); ?>" value="<?php esc_attr_e('Log In', 'theme-my-login'); ?>" />
				<input type="hidden" name="redirect_to" value="<?php $template->the_redirect_url('login'); ?>" />
				<input type="hidden" name="instance" value="<?php $template->the_instance(); ?>" />
				<input type="hidden" name="action" value="login" />
			</div>
		</div>
		<div class="text-sm text-gray-500 text-center mt-4"><?php $template->the_action_links(array('login' => false)); ?></div>
	</form>
</div>