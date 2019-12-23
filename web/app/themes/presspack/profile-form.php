<?php
/*
If you would like to edit this file, copy it to your current theme's directory and edit it there.
Theme My Login will always look in your theme's directory first, before using this default template.
*/
?>
<div class="tml tml-profile px-6 max-w-2xl mx-auto" id="theme-my-login<?php $template->the_instance(); ?>">
	<?php $template->the_action_template_message('profile'); ?>
	<?php $template->the_errors(); ?>
	<form id="your-profile" action="<?php $template->the_action_url('profile', 'login_post'); ?>" method="post">
		<?php wp_nonce_field('update-user_' . $current_user->ID); ?>
		<p>
			<input type="hidden" name="from" value="profile" />
			<input type="hidden" name="checkuser_id" value="<?php echo $current_user->ID; ?>" />
		</p>

		<!-- <h3 class="bold text-xl mt-6 mb-2"><?php _e('Personal Options', 'theme-my-login'); ?></h3>

		<table class="tml-form-table">
			<tr class="tml-user-admin-bar-front-wrap">
				<th><label for="admin_bar_front"><?php _e('Toolbar', 'theme-my-login') ?></label></th>
				<td>
					<label for="admin_bar_front"><input type="checkbox" name="admin_bar_front" id="admin_bar_front" value="1" <?php checked(_get_admin_bar_pref('front', $profileuser->ID)); ?> />
						<?php _e('Show Toolbar when viewing site', 'theme-my-login'); ?></label>
				</td>
			</tr>
			<?php do_action('personal_options', $profileuser); ?>
		</table> -->

		<?php do_action('profile_personal_options', $profileuser); ?>

		<h3 class="bold text-xl mt-6 mb-2"><?php _e('Basic Info', 'theme-my-login'); ?></h3>

		<div class="mb-4">
			<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for=" user_login"><?php _e('Username', 'theme-my-login'); ?></label>
			<input disabled class="appearance-none cursor-not-allowed block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" type=" text" name="user_login" id="user_login" value="<?php echo esc_attr($profileuser->user_login); ?>" disabled="disabled" class="regular-text" />
			<div class="text-sm text-gray-500"><?php _e('Usernames cannot be changed.', 'theme-my-login'); ?></div>
		</div>
		<div class="mb-4">
			<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="first_name"><?php _e('First Name', 'theme-my-login'); ?></label>
			<input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" type="text" name="first_name" id="first_name" value="<?php echo esc_attr($profileuser->first_name); ?>" class="regular-text" />
		</div>
		<div class="mb-4">
			<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="last_name"><?php _e('Last Name', 'theme-my-login'); ?></label>
			<input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" type="text" name="last_name" id="last_name" value="<?php echo esc_attr($profileuser->last_name); ?>" class="regular-text" />
		</div>

		<div class="mb-4">
			<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="nickname"><?php _e('Nickname', 'theme-my-login'); ?> <span class="description"><?php _e('(required)', 'theme-my-login'); ?></span></label>
			<input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" type="text" name="nickname" id="nickname" value="<?php echo esc_attr($profileuser->nickname); ?>" class="regular-text" />
		</div>

		<div class="mb-4">
			<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="display_name"><?php _e('Display name publicly as', 'theme-my-login'); ?></label>
			<div class="relative">
				<select class="block appearance-none w-full bg-gray-200 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500" name="display_name" id="display_name">
					<?php
					$public_display = array();
					$public_display['display_nickname']  = $profileuser->nickname;
					$public_display['display_username']  = $profileuser->user_login;

					if (!empty($profileuser->first_name))
						$public_display['display_firstname'] = $profileuser->first_name;

					if (!empty($profileuser->last_name))
						$public_display['display_lastname'] = $profileuser->last_name;

					if (!empty($profileuser->first_name) && !empty($profileuser->last_name)) {
						$public_display['display_firstlast'] = $profileuser->first_name . ' ' . $profileuser->last_name;
						$public_display['display_lastfirst'] = $profileuser->last_name . ' ' . $profileuser->first_name;
					}

					if (!in_array($profileuser->display_name, $public_display)) // Only add this if it isn't duplicated elsewhere
						$public_display = array('display_displayname' => $profileuser->display_name) + $public_display;

					$public_display = array_map('trim', $public_display);
					$public_display = array_unique($public_display);

					foreach ($public_display as $id => $item) {
					?>
						<option <?php selected($profileuser->display_name, $item); ?>><?php echo $item; ?></option>
					<?php
					}
					?>
				</select>
				<div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
					<svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
						<path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" /></svg>
				</div>
			</div>
		</div>

		<h3 class="bold text-xl mt-6 mb-2"><?php _e('Contact Info', 'theme-my-login'); ?></h3>
		<div class="mb-4">
			<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="email"><?php _e('E-mail', 'theme-my-login'); ?> <span class="description"><?php _e('(required)', 'theme-my-login'); ?></span></label>
			<input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" type="text" name="email" id="email" value="<?php echo esc_attr($profileuser->user_email); ?>" />
			<?php
			$new_email = get_option($current_user->ID . '_new_email');
			if ($new_email && $new_email['newemail'] != $current_user->user_email) : ?>
				<div class="updated inline">
					<p><?php
							printf(
								__('There is a pending change of your e-mail to %1$s. <a href="%2$s">Cancel</a>', 'theme-my-login'),
								'<code>' . $new_email['newemail'] . '</code>',
								esc_url(self_admin_url('profile.php?dismiss=' . $current_user->ID . '_new_email'))
							); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<div class="mb-4">
			<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="url"><?php _e('Website', 'theme-my-login'); ?></label>
			<input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" type="text" name="url" id="url" value="<?php echo esc_attr($profileuser->user_url); ?>" />
		</div>

		<?php
		foreach (wp_get_user_contact_methods() as $name => $desc) {
		?>
			<div class="tml-user-contact-method-<?php echo $name; ?>-wrap  mb-2">
				<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="<?php echo $name; ?>"><?php echo apply_filters('user_' . $name . '_label', $desc); ?></label>
				<input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" type="text" name="<?php echo $name; ?>" id="<?php echo $name; ?>" value="<?php echo esc_attr($profileuser->$name); ?>" class="regular-text" />
			</div>
		<?php
		}
		?>


		<h3 class="bold text-xl mt-6 mb-2"><?php _e('About Yourself', 'theme-my-login'); ?></h3>

		<div class="mb-4">
			<label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="description"><?php _e('Biographical Info', 'theme-my-login'); ?></label>
			<textarea class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" name="description" id="description" rows="5" cols="30"><?php echo esc_html($profileuser->description); ?></textarea>
			<span class="text-sm text-gray-500"><?php _e('Share a little biographical information to fill out your profile. This may be shown publicly.', 'theme-my-login'); ?></span>
		</div>

		<?php
		$show_password_fields = apply_filters('show_password_fields', true, $profileuser);
		if ($show_password_fields) :
		?>


			<h3 class="bold text-xl mt-6 mb-2"><?php _e('Account Management', 'theme-my-login'); ?></h3>
			<table class="tml-form-table">
				<tr id="password" class="user-pass1-wrap">
					<th><label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="pass1"><?php _e('New Password', 'theme-my-login'); ?></label></th>
					<td>
						<input class="hidden" value=" " /><!-- #24364 workaround -->
						<button type="button" class="button button-secondary wp-generate-pw hide-if-no-js  bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center"><?php _e('Generate Password', 'theme-my-login'); ?></button>
						<div class=" wp-pwd hide-if-js">
							<span class="password-input-wrapper">
								<input type="password" name="pass1" id="pass1" class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" value="" autocomplete="off" data-pw="<?php echo esc_attr(wp_generate_password(24)); ?>" aria-describedby="pass-strength-result" />
							</span>
							<div style="display:none" id="pass-strength-result" aria-live="polite"></div>
							<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js  " data-toggle="0" aria-label="<?php esc_attr_e('Hide password', 'theme-my-login'); ?>">
								<span class="dashicons dashicons-hidden"></span>
								<span class="text"><?php _e('Hide', 'theme-my-login'); ?></span>
							</button>
							<button type="button" class="button button-secondary wp-cancel-pw hide-if-no-js  bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center" data-toggle="0" aria-label="<?php esc_attr_e('Cancel password change', 'theme-my-login'); ?>">
								<span class="text"><?php _e('Cancel', 'theme-my-login'); ?></span>
							</button>
						</div>
					</td>
				</tr>
				<tr class="user-pass2-wrap hide-if-js">
					<th scope="row"><label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="pass2"><?php _e('Repeat New Password', 'theme-my-login'); ?></label></th>
					<td>
						<input name="pass2" type="password" id="pass2" class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" value="" autocomplete="off" />
						<p class="description"><?php _e('Type your new password again.', 'theme-my-login'); ?></p>
					</td>
				</tr>
				<tr class="pw-weak">
					<th><?php _e('Confirm Password', 'theme-my-login'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="pw_weak" class="pw-checkbox" />
							<?php _e('Confirm use of weak password', 'theme-my-login'); ?>
						</label>
					</td>
				</tr>
			<?php endif; ?>

			</table>

			<?php do_action('show_user_profile', $profileuser); ?>
			<hr class="my-6">
			<p class="tml-submit-wrap">
				<input type="hidden" name="action" value="profile" />
				<input type="hidden" name="instance" value="<?php $template->the_instance(); ?>" />
				<input type="hidden" name="user_id" id="user_id" value="<?php echo esc_attr($current_user->ID); ?>" />
				<input type="submit" class="button-primary   bg-blue-500 hover:bg-blue-400 focus:shadow-outline focus:outline-none text-white font-bold py-2 px-4 rounded" value=" <?php esc_attr_e('Update Profile', 'theme-my-login'); ?>" name="submit" id="submit" />
			</p>
	</form>
</div>