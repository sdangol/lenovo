<?php
/**
 * Settings class.
 *
 * @package    WPForms
 * @author     WPForms
 * @since      1.0.0
 * @license    GPL-2.0+
 * @copyright  Copyright (c) 2016, WPForms LLC
 */
class WPForms_Settings {

	/**
	 * Holds the plugin settings.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $options;

	/**
	 * The available forms.
	 *
	 * @since 1.3.7
	 * @var array
	 */
	public $forms = false;

	/**
	 * Template code if generated.
	 *
	 * @since 1.3.7
	 * @var string
	 */
	private $template = false;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Maybe load settings page
		add_action( 'admin_init', array( $this, 'init' ) );

		// Plugin settings link
		add_filter( 'plugin_action_links_' . plugin_basename( WPFORMS_PLUGIN_DIR . 'wpforms.php' ), array( $this, 'settings_link' ) );
	}

	/**
	 * Get the value of a specific setting.
	 *
	 * @since 1.0.0
	 * @param string $key
	 * @param bool $default
	 * @param string $option
	 * @return mixed
	 */
	public function get( $key, $default = false, $option = 'wpforms_settings' ) {

		if ( 'wpforms_settings' === $option && ! empty( $this->options ) ) {
			$options = $this->options;
		} else {
			$options = get_option( $option, false );
		}

		return ! empty( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	/**
	 * Determing if the user is viewing the settings page, if so, party on.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Check what page we are on.
		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';

		// Only load if we are actually on the settings page.
		if ( 'wpforms-settings' === $page ) {

			// Retrieve settings.
			$this->options = get_option( 'wpforms_settings', array() );

			// Retrieve available forms.
			$args  = array(
				'orderby' => 'title',
			);
			$this->forms = wpforms()->form->get( '', $args );

			add_action( 'wpforms_tab_settings_general',       array( $this, 'settings_page_tab_general'               ) );
			add_action( 'wpforms_tab_settings_payments',      array( $this, 'settings_page_tab_payments'              ) );
			add_action( 'wpforms_tab_settings_providers',     array( $this, 'settings_page_tab_providers'             ) );
			add_action( 'wpforms_tab_settings_import_export', array( $this, 'settings_page_tab_import_export'         ) );
			add_action( 'wpforms_tab_settings_system',        array( $this, 'settings_page_tab_system'                ) );
			add_action( 'wpforms_settings_init',              array( $this, 'settings_page_tab_import_export_process' ) );
			add_action( 'admin_enqueue_scripts',              array( $this, 'enqueues'                                ) );
			add_action( 'wpforms_admin_page',                 array( $this, 'output'                                  ) );

			// Hook for add-ons
			do_action( 'wpforms_settings_init' );
		}
	}

	/**
	 * Enqueue assets for the settings page.
	 *
	 * @since 1.0.0
	 */
	public function enqueues() {

		wp_enqueue_media();

		// CSS
		wp_enqueue_style(
			'font-awesome',
			WPFORMS_PLUGIN_URL . 'assets/css/font-awesome.min.css',
			null,
			'4.4.0'
		);

		wp_enqueue_style(
			'wpforms-settings',
			WPFORMS_PLUGIN_URL . 'assets/css/admin-settings.css',
			null,
			WPFORMS_VERSION
		);

		wp_enqueue_style(
			'minicolors',
			WPFORMS_PLUGIN_URL . 'assets/css/jquery.minicolors.css',
			null,
			'2.2.3'
		);

		// JS
		wp_enqueue_script(
			'minicolors',
			WPFORMS_PLUGIN_URL . 'assets/js/jquery.minicolors.min.js',
			array( 'jquery' ),
			'2.2.3',
			false
		);

		wp_enqueue_script(
			'wpforms-settings',
			WPFORMS_PLUGIN_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			WPFORMS_VERSION,
			false
		);
		wp_localize_script(
			'wpforms-settings',
			'wpforms_settings',
			array(
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'nonce'                  => wp_create_nonce( 'wpforms-settings' ),
				'saving'                 => __( 'Saving ...', 'wpforms' ),
				'provider_disconnect'    => __( 'Are you sure you want to disconnect this account?', 'wpforms' ),
				'upload_title'           => __( 'Upload or Choose Your Image', 'wpforms' ),
				'upload_button'          => __( 'Use Image', 'wpforms' ),
			)
		);

		// Hook for add-ons.
		do_action( 'wpforms_settings_enqueue' );
	}

	/**
	 * Handles generating the appropriate tabs and setting sections.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function setting_page_tabs() {

		// Define our base tabs.
		$tabs = array(
			'general'       => __( 'General', 'wpforms' ),
			'payments'      => __( 'Payments', 'wpforms' ),
			'providers'     => __( 'Integrations', 'wpforms' ),
			'import_export' => __( 'Import/Export', 'wpforms' ),
			'system'        => __( 'System Info', 'wpforms' ),
		);

		// Allow for addons and extensions to add additional tabs.
		$tabs = apply_filters( 'wpform_settings_tabs', $tabs );

		return $tabs;
	}

	/**
	 * Build the output for General tab on the settings page and check for save.
	 *
	 * @since 1.0.0
	 */
	public function settings_page_tab_general() {

		// Check for save, if found let's dance.
		if ( ! empty( $_POST['wpforms-settings-general-nonce'] ) ) {

			// Do we have a valid nonce and permission?.
			if ( ! wp_verify_nonce( $_POST['wpforms-settings-general-nonce'], 'wpforms-settings-general-nonce' ) || ! current_user_can( apply_filters( 'wpforms_manage_cap', 'manage_options' ) ) ) {

				// No funny business.
				printf( '<div class="error below-h2"><p>%s</p></div>', __( 'Settings check failed.', 'wpforms' ) );

			} else {

				// Save General Settings.
				if ( isset( $_POST['submit-general'] ) ) {

					// Prep and sanatize settings for save.
					$this->options['email-template']             = ! empty( $_POST['email-template'] ) ? esc_attr( $_POST['email-template'] ) : 'default';
					$this->options['email-header-image']         = ! empty( $_POST['email-header-image'] ) ? esc_url_raw( $_POST['email-header-image'] ) : '';
					$this->options['email-background-color']     = ! empty( $_POST['email-background-color'] ) ? wpforms_sanitize_hex_color( $_POST['email-background-color'] ) : '#e9eaec';
					$this->options['email-carbon-copy']          = ! empty( $_POST['email-carbon-copy'] ) ? '1' : false;
					$this->options['disable-css']                = ! empty( $_POST['disable-css'] ) ? intval( $_POST['disable-css'] ) : '1';
					$this->options['global-assets']              = ! empty( $_POST['global-assets'] ) ? '1' : false;
					$this->options['recaptcha-type']             = ! empty( $_POST['recaptcha-type'] ) ? esc_html( $_POST['recaptcha-type'] ) : 'v2';
					$this->options['recaptcha-site-key']         = ! empty( $_POST['recaptcha-site-key'] ) ? esc_html( $_POST['recaptcha-site-key'] ) : '';
					$this->options['recaptcha-secret-key']       = ! empty( $_POST['recaptcha-secret-key'] ) ? esc_html( $_POST['recaptcha-secret-key'] ) : '';
					$this->options['validation-required']        = ! empty( $_POST['validation-required'] ) ? sanitize_text_field( $_POST['validation-required'] ) : '';
					$this->options['validation-url']             = ! empty( $_POST['validation-url'] ) ? sanitize_text_field( $_POST['validation-url'] ) : '';
					$this->options['validation-email']           = ! empty( $_POST['validation-email'] ) ? sanitize_text_field( $_POST['validation-email'] ) : '';
					$this->options['validation-number']          = ! empty( $_POST['validation-number'] ) ? sanitize_text_field( $_POST['validation-number'] ) : '';
					$this->options['validation-confirm']         = ! empty( $_POST['validation-confirm'] ) ? sanitize_text_field( $_POST['validation-confirm'] ) : '';
					$this->options['validation-fileextension']   = ! empty( $_POST['validation-fileextension'] ) ? sanitize_text_field( $_POST['validation-fileextension'] ) : '';
					$this->options['validation-filesize']        = ! empty( $_POST['validation-filesize'] ) ? sanitize_text_field( $_POST['validation-filesize'] ) : '';
					$this->options['validation-time12h']         = ! empty( $_POST['validation-time12h'] ) ? sanitize_text_field( $_POST['validation-time12h'] ) : '';
					$this->options['validation-time24h']         = ! empty( $_POST['validation-time24h'] ) ? sanitize_text_field( $_POST['validation-time24h'] ) : '';
					$this->options['validation-requiredpayment'] = ! empty( $_POST['validation-requiredpayment'] ) ? sanitize_text_field( $_POST['validation-requiredpayment'] ) : '';
					$this->options['validation-creditcard']      = ! empty( $_POST['validation-creditcard'] ) ? sanitize_text_field( $_POST['validation-creditcard'] ) : '';

					$this->options = apply_filters( 'wpforms_settings_save', $this->options, $_POST, 'general' );

					// Update settings in DB.
					update_option( 'wpforms_settings' , $this->options );

					printf( '<div class="updated below-h2"><p>%s</p></div>', __( 'General settings updated.', 'wpforms' ) );

				} elseif ( isset( $_POST['submit-key-verify'] ) ) {

					if ( ! empty( $_POST['license-key'] ) ) {
						wpforms()->license->verify_key( $_POST['license-key'] );
					} else {
						printf( '<div class="error below-h2"><p>%s</p></div>', __( 'Please enter a license key to verify.', 'wpforms' ) );
					}
				} elseif ( isset( $_POST['submit-key-deactivate'] ) ) {

					wpforms()->license->deactivate_key();

				} elseif ( isset( $_POST['submit-key-refresh'] ) ) {

					wpforms()->license->validate_key( $_POST['license-key'], true);
				}
			} // End if().
		} // End if().

		if ( class_exists( 'WPForms_License' ) ) {
			wpforms()->license->notices( true );
		}
		?>

		<div id="wpforms-settings-general">

			<form method="post">

				<?php wp_nonce_field( 'wpforms-settings-general-nonce', 'wpforms-settings-general-nonce' ); ?>

				<table class="form-table">
					<tbody>
						<?php if ( class_exists( 'WPForms_License' ) ) : ?>
						<tr>
							<td class="section" colspan="2">
								<h4><?php _e( 'License', 'wpforms' ); ?></h4>
								<p><?php _e( 'Your license key provides access to updates and Add-ons. ', 'wpforms' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-key"><?php _e( 'License Key', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="password" name="license-key" id="wpforms-settings-general-key" value="<?php echo $this->get( 'key', '', 'wpforms_license' ); ?>" />
									<?php submit_button( __( 'Verify Key', 'wpforms' ), 'primary', 'submit-key-verify', false ); ?>
									<?php if ( $this->get( 'key', false, 'wpforms_license' ) ) : ?>
										<?php submit_button( __( 'Deactivate Key', 'wpforms' ), 'secondary', 'submit-key-deactivate', false ); ?>
									<?php endif; ?>
								</form>
							</td>
						</tr>
						<?php if ( $this->get( 'type', false, 'wpforms_license' ) ) : ?>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-key-type"><?php _e( 'License Key Type', 'wpforms' ); ?></label>
							</th>
							<td>
								<span class="wpforms-key-type"><?php printf( __( 'Your license key type for this site is <strong>%s.</strong>', 'wpforms' ), $this->get( 'type', 'unknown', 'wpforms_license' ) ); ?></span>
								<?php submit_button( __( 'Refresh Key', 'wpforms' ), 'secondary', 'submit-key-refresh', false ); ?>
								<p class="description"><?php _e( 'If your license has been upgraded or is incorrect, you may force a refresh.', 'wpforms' ); ?></p>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<td class="section" colspan="2">
								<hr>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-css"><?php _e( 'Include Form Styling', 'wpforms' ); ?></label>
							</th>
							<td>
								<select name="disable-css" id="wpforms-settings-general-css">
									<option value="1" <?php selected( '1', $this->get( 'disable-css' ) ); ?>><?php _e( 'Base and form theme styling', 'wpforms' ); ?></option>
									<option value="2" <?php selected( '2', $this->get( 'disable-css' ) ); ?>><?php _e( 'Base styling only', 'wpforms' ); ?></option>
									<option value="3" <?php selected( '3', $this->get( 'disable-css' ) ); ?>><?php _e( 'None', 'wpforms' ); ?></option>
								</select>
								<p class="description"><?php _e( 'Determines which CSS files to load for the site.', 'wpforms' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-global-assets"><?php _e( 'Load Assets Globally', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="checkbox" name="global-assets" id="wpforms-settings-general-global-assets" value="1" <?php checked( '1', $this->get( 'global-assets' ) ); ?>>
								<label for="wpforms-settings-general-global-assets"><?php _e( 'Check this if you would like to load WPForms assets site-wide. Only check if your site is having compatibility issues or instructed to by support.', 'wpforms' ); ?></label>
							</td>
						</tr>
						<tr>
							<td class="section" colspan="2">
								<hr>
								<h4><?php _e( 'Email', 'wpforms' ); ?></h4>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-email-template"><?php _e( 'Email Template', 'wpforms' ); ?></label>
							</th>
							<td>
								<select name="email-template" id="wpforms-settings-general-email-template">
									<option value="default" <?php selected( 'default', $this->get( 'email-template' ) ); ?>><?php _e( 'Default HTML template', 'wpforms' ); ?></option>
									<option value="none" <?php selected( 'none', $this->get( 'email-template' ) ); ?>><?php _e( 'Plain Text', 'wpforms' ); ?></option>
								</select>
								<p class="description"><?php _e( 'Determines how email notifications will be formatted.', 'wpforms' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-email-header-image"><?php _e( 'Email Header Image ', 'wpforms' ); ?></label>
							</th>
							<td>
								<label for="wpforms-settings-general-email-header-image" class="wpforms-settings-upload-image-display">
									<?php
									$email_header = $this->get( 'email-header-image' );
									if ( $email_header ) {
										echo '<img src="' . esc_url_raw( $email_header ) . '">';
									}
									?>
								</label>
								<input type="text" name="email-header-image" id="wpforms-settings-general-email-header-image" class="wpforms-settings-upload-image-value" value="<?php echo esc_url_raw( $this->get( 'email-header-image' ) ); ?>" />
								<a href="#" class="button button-secondary wpforms-settings-upload-image"><?php _e( 'Upload Image', 'wpforms' ); ?></a>
								<p class="description">
									<?php _e( 'Upload or choose a logo to be displayed at the top of email notifications.', 'wpforms' ); ?><br>
									<?php _e( 'Recommended size is 300x100 or smaller for best support on all devices.', 'wpforms' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-email-background-color"><?php _e( 'Email Background Color', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="email-background-color" value="<?php echo esc_attr( $this->get( 'email-background-color', '#e9eaec' ) ); ?>" id="wpforms-settings-general-email-background-color" class="wpforms-color-picker">
								<p class="description"><?php _e( 'Customize the background color of the HTML email template.', 'wpforms' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-email-carbon-copy"><?php _e( 'Email Carbon Copy', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="checkbox" name="email-carbon-copy" id="wpforms-settings-general-email-carbon-copy" value="1" <?php checked( '1', $this->get( 'email-carbon-copy' ) ); ?>>
								<label for="wpforms-settings-general-email-carbon-copy"><?php _e( 'Check this if you would like to enable the ability to CC: email addresses in the form notification settings.', 'wpforms' ); ?></label>
							</td>
						</tr>
						<tr>
							<td class="section" colspan="2">
								<hr>
								<h4><?php _e( 'reCAPTCHA', 'wpforms' ); ?></h4>
								<p><?php _e( 'reCAPTCHA is a free anti-spam service from Google which helps to protect your website from spam and abuse while letting real people pass through with ease.', 'wpforms' ); ?></p>
								<p><?php _e( 'Google\'s original <a href="https://www.google.com/recaptcha/intro/" rel="noopener noreferrer">v2 reCAPTCHA</a> prompts users to check a box to prove they\'re human, whereas <a href="https://www.google.com/recaptcha/intro/invisible.html" rel="noopener noreferrer">Invisible reCAPTCHA</a> uses advanced technology to detect real users without requiring any input.', 'wpforms' ); ?></p>
								<p><?php _e( 'Sites already using v2 reCAPTCHA will need to create new site keys before switching to the Invisible reCAPTCHA.', 'wpforms' ); ?></p>
								<p><?php _e( '<a href="https://wpforms.com/docs/setup-captcha-wpforms/" rel="noopener noreferrer">Read our walk through</a> to learn more and for step-by-step directions.', 'wpforms' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-recaptcha-type"><?php _e( 'reCAPTCHA Type', 'wpforms' ); ?></label>
							</th>
							<td>
								<select name="recaptcha-type" id="wpforms-settings-general-recaptcha-type">
									<?php
									$options = array(
										'v2'        => 'v2 reCAPTCHA',
										'invisible' => 'Invisible reCAPTCHA',
									);
									$selected = $this->get( 'recaptcha-type' );
									foreach ( $options as $key => $option ) {
										printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $selected, $key, false ), esc_html( $option ) );
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-recaptcha-site-key"><?php _e( 'reCAPTCHA Site key', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="recaptcha-site-key" value="<?php echo esc_attr( $this->get( 'recaptcha-site-key' ) ); ?>" id="wpforms-settings-general-recaptcha-site-key">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-recaptcha-secret-key"><?php _e( 'reCAPTCHA Secret key', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="recaptcha-secret-key" value="<?php echo esc_attr( $this->get( 'recaptcha-secret-key' ) ); ?>" id="wpforms-settings-general-recaptcha-secret-key">
							</td>
						</tr>
						<tr>
							<td class="section" colspan="2">
								<hr>
								<h4><?php _e( 'Validation Messages', 'wpforms' ); ?></h4>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-required"><?php _e( 'Required', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-required" value="<?php echo esc_attr( $this->get( 'validation-required', __( 'This field is required.', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-required">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-url"><?php _e( 'Website URL', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-url" value="<?php echo esc_attr( $this->get( 'validation-url', __( 'Please enter a valid URL.', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-url">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-email"><?php _e( 'Email', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-email" value="<?php echo esc_attr( $this->get( 'validation-email', __( 'Please enter a valid email address.', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-email">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-number"><?php _e( 'Number', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-number" value="<?php echo esc_attr( $this->get( 'validation-number', __( 'Please enter a valid number.', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-number">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-confirm"><?php _e( 'Confirm Value', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-confirm" value="<?php echo esc_attr( $this->get( 'validation-confirm', __( 'Field values do not match.', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-confirm">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-fileextension"><?php _e( 'File Extension', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-fileextension" value="<?php echo esc_attr( $this->get( 'validation-fileextension', __( 'File type is not allowed.', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-fileextension">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-filesize"><?php _e( 'File Size', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-filesize" value="<?php echo esc_attr( $this->get( 'validation-filesize', __( 'File exceeds max size allowed.', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-filesize">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-time12h"><?php _e( 'Time (12 hour)', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-time12h" value="<?php echo esc_attr( $this->get( 'validation-time12h', __( 'Please enter time in 12-hour AM/PM format (eg 8:45 AM).', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-time12h">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-time24h"><?php _e( 'Time (24 hour)', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-time24h" value="<?php echo esc_attr( $this->get( 'validation-time24h', __( 'Please enter time in 24-hour format (eg 22:45).', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-time24h">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-requiredpayment"><?php _e( 'Payment Required', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-requiredpayment" value="<?php echo esc_attr( $this->get( 'validation-requiredpayment', __( 'Payment is required.', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-requiredpayment">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpforms-settings-general-validation-creditcard"><?php _e( 'Credit Card', 'wpforms' ); ?></label>
							</th>
							<td>
								<input type="text" name="validation-creditcard" value="<?php echo esc_attr( $this->get( 'validation-creditcard', __( 'Please enter a valid credit card number.', 'wpforms' ) ) ); ?>" id="wpforms-settings-general-validation-creditcard">
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save General Settings', 'wpforms'), 'primary', 'submit-general' ); ?>

			</form>

		</div>
		<?php
	}

	/**
	 * Build the output for Payments tab on the settings page and check for save.
	 *
	 * @since 1.0.0
	 */
	public function settings_page_tab_payments() {

		// Check for save, if found let's dance.
		if ( ! empty( $_POST['wpforms-settings-payments-nonce'] ) && isset( $_POST['submit-payments'] ) ) {

			// Do we have a valid nonce and permission?
			if ( ! wp_verify_nonce( $_POST['wpforms-settings-payments-nonce'], 'wpforms-settings-payments-nonce' ) || ! current_user_can( apply_filters( 'wpforms_manage_cap', 'manage_options' ) ) ) {

				// No funny business.
				printf( '<div class="error below-h2"><p>%s</p></div>', __( 'Settings check failed.', 'wpforms' ) );

			} else {

				$this->options['currency'] = ! empty( $_POST['currency'] ) ? esc_html( $_POST['currency'] ) : 'USD';
				$this->options = apply_filters( 'wpforms_settings_save', $this->options, $_POST, 'payments' );

				// Update settings in DB.
				update_option( 'wpforms_settings' , $this->options );

				// Winning.
				printf( '<div class="updated below-h2"><p>%s</p></div>', __( 'Settings updated.', 'wpforms' ) );
			}
		}
		?>

		<div id="wpforms-settings-payments">

			<form method="post">

				<table class="form-table">
					<tbody>

						<tr>
							<th scope="row">
								<label for="wpforms-settings-payments-currency"><?php _e( 'Currency', 'wpforms' ); ?></label>
							</th>
							<td>
								<select name="currency" id="wpforms-settings-payments-currency">
									<?php
									$currencies = wpforms_get_currencies();
									$selected   = $this->get( 'currency', 'USD' );
									foreach ( $currencies as $code => $currency ) {
										printf( '<option value="%s" %s>%s (%s %s)</option>', $code, selected( $selected, $code, false ), $currency['name'], $code, $currency['symbol']  );
									}
									?>
								</select>
								<p class="description"><?php _e( 'Determines which currency to use for payments.', 'wpforms' ); ?></p>
							</td>
						</tr>

						<?php do_action( 'wpforms_payments_settings_table', $this->options ); ?>

					</tbody>
				</table>

				<?php wp_nonce_field( 'wpforms-settings-payments-nonce', 'wpforms-settings-payments-nonce' ); ?>

				<?php submit_button( __( 'Save', 'wpforms'), 'primary', 'submit-payments' ); ?>

			</form>

		</div>
		<?php
	}

	/**
	 * Build the output for Integrations (providers) tab on the settings page and check for save.
	 *
	 * @since 1.0.0
	 */
	public function settings_page_tab_providers() {

		$providers = get_option( 'wpforms_providers', false );
		$active    = apply_filters( 'wpforms_providers_available', array() );

		// If no provider addons are activated display a message and bail.
		if ( empty( $active ) ) {
			echo '<div class="notice notice-info below-h2"><p>' . sprintf( __( 'You do not have any marketing add-ons activated. You can head over to the <a href="%s">Add-Ons page</a> to install and activate the add-on for your provider.', 'wpforms' ), admin_url( 'admin.php?page=wpforms-addons' ) ) . '</p></div>';
			return;
		}
		?>

		<div id="wpforms-settings-providers">
			<?php do_action( 'wpforms_settings_providers', $active, $providers ); ?>
		</div>
		<?php
	}

	/**
	 * Build the output for System Info (system) tab on the settings page.
	 *
	 * @since 1.2.4
	 */
	public function settings_page_tab_import_export() {

		if ( isset( $_GET['wpforms_notice'] ) && 'forms-imported' === $_GET['wpforms_notice'] ) {
			printf( '<div class="updated below-h2"><p>%s</p></div>', __( 'Form(s) imported', 'wpforms' ) );
		}
		?>
		<div id="wpforms-import-export">

			<table class="form-table">
				<tbody>
					<tr>
						<td class="section" colspan="2">
							<h4><?php _e( 'Form Import', 'wpforms' ); ?></h4>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="padding-left:0;">
							<p><?php _e( 'Select an export file.', 'wpforms' ); ?></p>
							<form method="post" enctype="multipart/form-data" action="<?php echo admin_url( 'admin.php?page=wpforms-settings#!wpforms-tab-import_export' ); ?>">
									<input type="file" name="file" id="wpforms-import_export-form-import" accept=".json">
									<input type="hidden" name="action" value="import_form">
									<?php wp_nonce_field( 'wpforms_import_nonce', 'wpforms-settings-importexport-nonce' ); ?>
									<br>
									<?php submit_button( __( 'Import', 'wpforms' ), 'secondary', 'submit-importexport', false ); ?>
							</form>
						</td>
					</tr>
					<tr>
						<td class="section" colspan="2">
							<hr>
							<h4><?php _e( 'Form Export', 'wpforms' ); ?></h4>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="padding-left:0;">
							<p><?php _e( 'Select form(s) to download an export file. This can be imported into another site.' ,'wpforms' ); ?></p>
							<form method="post" action="<?php echo admin_url( 'admin.php?page=wpforms-settings#!wpforms-tab-import_export' ); ?>">
								<ul class="export-list">
								<?php
								if ( $this->forms ) {
									foreach ( $this->forms as $form ) {
										printf( '<li><label><input type="checkbox" name="forms[]" value="%d"> %s</label></li>', $form->ID, esc_html( $form->post_title ) );
									}
								} else {
									echo '<li><label>' . __( 'No forms', 'wpforms' ) . '</label></li>';
								}
								?>
								</ul>
								<input type="hidden" name="action" value="export_form">
								<?php wp_nonce_field( 'wpforms_import_nonce', 'wpforms-settings-importexport-nonce' ); ?>
								<?php submit_button( __( 'Export', 'wpforms' ), 'secondary', 'submit-importexport', false ); ?>
							</form>
						</td>
					</tr>
					<tr>
						<td class="section" colspan="2">
							<hr>
							<h4 id="template-export"><?php _e( 'Form Template Export', 'wpforms' ); ?></h4>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="padding-left:0;">
							<?php
							if ( $this->template ) {
								echo '<p>' . __( 'The following code can be used to register your custom form template. Copy and paste the following code to your theme\'s functions.php file or include it within an external file.', 'wpforms' ) . '<p>';
								echo '<p>' . sprintf( __( 'For more information <a href="%s" target="blank" rel="noopener noreferrer">see our documentation</a> for additional details.', 'wpforms' ), 'https://wpforms.com/docs/how-to-create-a-custom-form-template/' ) . '<p>';
								echo '<textarea class="template-export-textarea" style="font-size:11px;" readonly>' . esc_textarea( $this->template ) . '</textarea><br>';
							}
							?>
							<p><?php _e( 'Select a form to generate PHP code that can be used to register a custom form template.', 'wpforms' ); ?></p>
							<form method="post" action="<?php echo admin_url( 'admin.php?page=wpforms-settings&jump=template-export#!wpforms-tab-import_export' ); ?>">
								<?php
								if ( ! empty( $this->forms ) ) {
									echo '<select id="wpforms-settings-importextport-template" name="form">';
									foreach ( $this->forms as $form ) {
										printf( '<option value="%d">%s</option>', $form->ID, esc_html( $form->post_title ) );
									}
									echo '</select>';
								} else {
									echo '<p>' . __( 'You need to create a form before you can generate a template.'. 'wpforms' ) . '</p>';
								}
								?>
								<input type="hidden" name="action" value="export_template"><br>
								<?php wp_nonce_field( 'wpforms_import_nonce', 'wpforms-settings-importexport-nonce' ); ?>
								<?php submit_button( __( 'Generate template code', 'wpforms' ), 'secondary', 'submit-importexport', false ); ?>
							</form>
						</td>
					</tr>
				</tbody>
			</table>

		</div>
		<?php
	}

	/**
	 * Import/Export processing.
	 *
	 * @since 1.2.4
	 */
	public function settings_page_tab_import_export_process() {

		// Check for triggered save.
		if ( empty( $_POST['wpforms-settings-importexport-nonce'] ) || empty( $_POST['action'] ) || ! isset( $_POST['submit-importexport'] ) ) {
			return;
		}

		// Check for valid nonce and permission.
		if ( ! wp_verify_nonce( $_POST['wpforms-settings-importexport-nonce'], 'wpforms_import_nonce' ) || ! current_user_can( apply_filters( 'wpforms_manage_cap', 'manage_options' ) ) ) {
			return;
		}

		// Export Form(s).
		if ( 'export_form' === $_POST['action'] && ! empty( $_POST['forms'] ) ) {

			$export = array();
			$forms  = get_posts(
				array(
					'post_type'     => 'wpforms',
					'no_found_rows' => true,
					'nopaging'      => true,
					'post__in'      => array_map( 'intval', $_POST['forms'] ),
			) );

			foreach ( $forms as $form ) {
				$export[] = wpforms_decode( $form->post_content );
			}

			ignore_user_abort( true );

			if ( ! in_array( 'set_time_limit', explode( ',', ini_get( 'disable_functions' ) ) ) ) {
				set_time_limit( 0 );
			}

			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=wpforms-form-export-' . date( 'm-d-Y' ) . '.json' );
			header( 'Expires: 0' );

			echo wp_json_encode( $export );
			exit;
		}

		// Import Form(s).
		if ( 'import_form' === $_POST['action'] && ! empty( $_FILES['file']['tmp_name'] ) ) {

			$ext = strtolower( pathinfo( $_FILES['file']['name'], PATHINFO_EXTENSION ) );

			if ( 'json' !== $ext ) {
				wp_die( __( 'Please upload a valid .json form export file.', 'wpforms' ), __( 'Error', 'wpformsp' ), array( 'response' => 400 ) );
			}

			$forms = json_decode( file_get_contents( $_FILES['file']['tmp_name'] ), true );

			if ( ! empty( $forms ) ) {

				foreach ( $forms as $form ) {

					$title  = ! empty( $form['settings']['form_title'] ) ? $form['settings']['form_title'] : '';
					$desc   = ! empty( $form['settings']['form_desc'] ) ? $form['settings']['form_desc'] : '';
					$new_id = wp_insert_post( array(
							'post_title'   => $title,
							'post_status'  => 'publish',
							'post_type'    => 'wpforms',
							'post_excerpt' => $desc,
					) );
					if ( $new_id ) {
						$form['id'] = $new_id;
						$new = array(
							'ID'           => $new_id,
							'post_content' => wpforms_encode( $form ),
						);
						wp_update_post( $new );
					}
				}
				wp_safe_redirect( admin_url( 'admin.php?page=wpforms-settings&wpforms_notice=forms-imported#!wpforms-tab-import_export' ) );
				exit;
			}
		}

		// Export form template.
		if ( 'export_template' === $_POST['action'] && ! empty( $_POST['form'] ) ) {

			$args = array(
				'content_only' => true,
			);
			$form_data = wpforms()->form->get( absint( $_POST['form'] ), $args );

			if ( ! $form_data ) {
				return;
			}

			// Define basic data.
			$name  = sanitize_text_field( $form_data['settings']['form_title'] );
			$desc  = sanitize_text_field( $form_data['settings']['form_desc'] );
			$slug  = sanitize_key( str_replace( ' ', '_' , $form_data['settings']['form_title'] ) );
			$class = 'WPForms_Template_' . $slug;

			// Format template field and settings data.
			$data = $form_data;
			$data['meta']['template'] = $slug;
			unset( $data['id'] );
			$data = var_export( $data, true );
			$data = str_replace( '  ', "\t", $data );
			$data = preg_replace( '/([\t\r\n]+?)array/', 'array', $data );

			// Build the final template string.
			$this->template = <<<EOT
if ( class_exists( 'WPForms_Template' ) ) :
/**
 * {$name}
 * Template for WPForms.
 */
class {$class} extends WPForms_Template {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Template name
		\$this->name = '{$name}';

		// Template slug
		\$this->slug = '{$slug}';

		// Template description
		\$this->description = '{$desc}';

		// Template field and settings
		\$this->data = {$data};
	}
}
new {$class};
endif;
EOT;
		} // End if().
	}

	/**
	 * Build the output for System Info (system) tab on the settings page.
	 *
	 * @since 1.2.3
	 */
	public function settings_page_tab_system() {

		?>
		<div id="wpforms-settings-system">
			<textarea readonly="readonly" class="system-info-textarea"><?php echo $this->get_system_info(); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Build the output for the plugin settings page.
	 *
	 * @since 1.0.0
	 */
	public function output() {

		?>
		<div id="wpforms-settings" class="wrap">

			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

			<div class="wpforms-circle-loader">
				<div class="wpforms-circle-1 wpforms-circle"></div>
				<div class="wpforms-circle-2 wpforms-circle"></div>
				<div class="wpforms-circle-3 wpforms-circle"></div>
				<div class="wpforms-circle-4 wpforms-circle"></div>
				<div class="wpforms-circle-5 wpforms-circle"></div>
				<div class="wpforms-circle-6 wpforms-circle"></div>
				<div class="wpforms-circle-7 wpforms-circle"></div>
				<div class="wpforms-circle-8 wpforms-circle"></div>
				<div class="wpforms-circle-9 wpforms-circle"></div>
				<div class="wpforms-circle-10 wpforms-circle"></div>
				<div class="wpforms-circle-11 wpforms-circle"></div>
				<div class="wpforms-circle-12 wpforms-circle"></div>
			</div>

			<div id="wpforms-tabs" class="wpforms-clear">

				<!-- Output tabs navigation -->
				<h2 id="wpforms-tabs-nav" class="wpforms-clear nav-tab-wrapper">
					<?php $i = 0; foreach ( (array) $this->setting_page_tabs() as $id => $title ) : $class = 0 === $i ? 'wpforms-active nav-tab-active' : ''; ?>
						<a class="nav-tab <?php echo $class; ?>" href="#wpforms-tab-<?php echo $id; ?>" title="<?php echo $title; ?>"><?php echo $title; ?></a>
					<?php $i++; endforeach; ?>
				</h2>

				<!-- Output tab sections -->
				<?php $i = 0; foreach ( (array) $this->setting_page_tabs() as $id => $title ) : $class = 0 === $i ? 'wpforms-active' : ''; ?>
				<div id="wpforms-tab-<?php echo $id; ?>" class="wpforms-tab wpforms-clear <?php echo $class; ?>">
					<?php do_action( 'wpforms_tab_settings_' . $id ); ?>
				</div>
				<?php $i++; endforeach; ?>

			</div>

		</div>
		<?php
	}

	/**
	 * Add settings link to the Plugins page.
	 *
	 * @since 1.0.0
	 * @param array $links
	 * @return array $links
	 */
	public function settings_link( $links ) {

		$admin_link  = add_query_arg(
			array(
				'page' => 'wpforms-settings',
			),
			admin_url( 'admin.php' )
		);

		$setting_link = sprintf(
			'<a href="%s">%s</a>',
			$admin_link ,
			__( 'Settings', 'wpforms' )
		);

		array_unshift( $links, $setting_link );

		return $links;
	}

	/**
	 * Get system information.
	 *
	 * Based on a function from Easy Digital Downloads by Pippin Williamson.
	 *
	 * @link https://github.com/easydigitaldownloads/easy-digital-downloads/blob/master/includes/admin/tools.php#L470
	 * @since 1.2.3
	 * @return string
	 */
	public function get_system_info() {

		global $wpdb;

		// Get theme info.
		$theme_data = wp_get_theme();
		$theme      = $theme_data->Name . ' ' . $theme_data->Version;

		$return  = '### Begin System Info ###' . "\n\n";

		// WPForms info.
		$activated = get_option( 'wpforms_activated', array() );
		$return .= '-- WPForms Info' . "\n\n";
		if ( ! empty( $activated['pro'] ) ) {
			$date    = $activated['pro'] + ( get_option( 'gmt_offset' ) * 3600 );
			$return .= 'Pro:                      ' . date_i18n( __( 'M j, Y @ g:ia' ), $date ) . "\n";
		}
		if ( ! empty( $activated['lite'] ) ) {
			$date    = $activated['lite'] + ( get_option( 'gmt_offset' ) * 3600 );
			$return .= 'Lite:                     ' . date_i18n( __( 'M j, Y @ g:ia' ), $date ) . "\n";
		}

		// Now the basics...
		$return .= '-- Site Info' . "\n\n";
		$return .= 'Site URL:                 ' . site_url() . "\n";
		$return .= 'Home URL:                 ' . home_url() . "\n";
		$return .= 'Multisite:                ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";

		// WordPress configuration.
		$return .= "\n" . '-- WordPress Configuration' . "\n\n";
		$return .= 'Version:                  ' . get_bloginfo( 'version' ) . "\n";
		$return .= 'Language:                 ' . ( defined( 'WPLANG' ) && WPLANG ? WPLANG : 'en_US' ) . "\n";
		$return .= 'Permalink Structure:      ' . ( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'Default' ) . "\n";
		$return .= 'Active Theme:             ' . $theme . "\n";
		$return .= 'Show On Front:            ' . get_option( 'show_on_front' ) . "\n";
		// Only show page specs if frontpage is set to 'page'.
		if ( get_option( 'show_on_front' ) === 'page' ) {
			$front_page_id = get_option( 'page_on_front' );
			$blog_page_id = get_option( 'page_for_posts' );
			$return .= 'Page On Front:            ' . ( 0 != $front_page_id ? get_the_title( $front_page_id ) . ' (#' . $front_page_id . ')' : 'Unset' ) . "\n";
			$return .= 'Page For Posts:           ' . ( 0 != $blog_page_id ? get_the_title( $blog_page_id ) . ' (#' . $blog_page_id . ')' : 'Unset' ) . "\n";
		}
		$return .= 'ABSPATH:                  ' . ABSPATH . "\n";
		$return .= 'Table Prefix:             ' . 'Length: ' . strlen( $wpdb->prefix ) . '   Status: ' . ( strlen( $wpdb->prefix ) > 16 ? 'ERROR: Too long' : 'Acceptable' ) . "\n";
		$return .= 'WP_DEBUG:                 ' . ( defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";
		$return .= 'WPFORMS_DEBUG:            ' . ( defined( 'WPFORMS_DEBUG' ) ? WPFORMS_DEBUG ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";
		$return .= 'Memory Limit:             ' . WP_MEMORY_LIMIT . "\n";
		$return .= 'Registered Post Stati:    ' . implode( ', ', get_post_stati() ) . "\n";

		// @todo WPForms configuration/specific details.
		$return .= "\n" . '-- WordPress Uploads/Constants' . "\n\n";
		$return .= 'WP_CONTENT_DIR:           ' . ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR ? WP_CONTENT_DIR : 'Disabled' : 'Not set' ) . "\n";
		$return .= 'WP_CONTENT_URL:           ' . ( defined( 'WP_CONTENT_URL' ) ? WP_CONTENT_URL ? WP_CONTENT_URL : 'Disabled' : 'Not set' ) . "\n";
		$return .= 'UPLOADS:                  ' . ( defined( 'UPLOADS' ) ? UPLOADS ? UPLOADS : 'Disabled' : 'Not set' ) . "\n";
		$uploads_dir = wp_upload_dir();
		$return .= 'wp_uploads_dir() path:    ' . $uploads_dir['path'] . "\n";
		$return .= 'wp_uploads_dir() url:     ' . $uploads_dir['url'] . "\n";
		$return .= 'wp_uploads_dir() basedir: ' . $uploads_dir['basedir'] . "\n";
		$return .= 'wp_uploads_dir() baseurl: ' . $uploads_dir['baseurl'] . "\n";

		// Get plugins that have an update.
		$updates = get_plugin_updates();

		// Must-use plugins.
		// NOTE: MU plugins can't show updates!
		$muplugins = get_mu_plugins();
		if ( count( $muplugins ) > 0 && ! empty( $muplugins ) ) {
			$return .= "\n" . '-- Must-Use Plugins' . "\n\n";

			foreach ( $muplugins as $plugin => $plugin_data ) {
				$return .= $plugin_data['Name'] . ': ' . $plugin_data['Version'] . "\n";
			}
		}

		// WordPress active plugins.
		$return .= "\n" . '-- WordPress Active Plugins' . "\n\n";

		$plugins = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		foreach ( $plugins as $plugin_path => $plugin ) {
			if ( ! in_array( $plugin_path, $active_plugins, true ) ) {
				continue;
			}
			$update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
			$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
		}

		// WordPress inactive plugins.
		$return .= "\n" . '-- WordPress Inactive Plugins' . "\n\n";

		foreach ( $plugins as $plugin_path => $plugin ) {
			if ( in_array( $plugin_path, $active_plugins ) ) {
				continue;
			}
			$update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
			$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
		}

		if ( is_multisite() ) {
			// WordPress Multisite active plugins.
			$return .= "\n" . '-- Network Active Plugins' . "\n\n";

			$plugins = wp_get_active_network_plugins();
			$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

			foreach ( $plugins as $plugin_path ) {
				$plugin_base = plugin_basename( $plugin_path );
				if ( ! array_key_exists( $plugin_base, $active_plugins ) ) {
					continue;
				}
				$update = ( array_key_exists( $plugin_path, $updates ) ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
				$plugin  = get_plugin_data( $plugin_path );
				$return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
			}
		}

		// Server configuration (really just versioning).
		$return .= "\n" . '-- Webserver Configuration' . "\n\n";
		$return .= 'PHP Version:              ' . PHP_VERSION . "\n";
		$return .= 'MySQL Version:            ' . $wpdb->db_version() . "\n";
		$return .= 'Webserver Info:           ' . $_SERVER['SERVER_SOFTWARE'] . "\n";

		// PHP configs... now we're getting to the important stuff.
		$return .= "\n" . '-- PHP Configuration' . "\n\n";
		$return .= 'Memory Limit:             ' . ini_get( 'memory_limit' ) . "\n";
		$return .= 'Upload Max Size:          ' . ini_get( 'upload_max_filesize' ) . "\n";
		$return .= 'Post Max Size:            ' . ini_get( 'post_max_size' ) . "\n";
		$return .= 'Upload Max Filesize:      ' . ini_get( 'upload_max_filesize' ) . "\n";
		$return .= 'Time Limit:               ' . ini_get( 'max_execution_time' ) . "\n";
		$return .= 'Max Input Vars:           ' . ini_get( 'max_input_vars' ) . "\n";
		$return .= 'Display Errors:           ' . ( ini_get( 'display_errors' ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A' ) . "\n";

		// PHP extensions and such.
		$return .= "\n" . '-- PHP Extensions' . "\n\n";
		$return .= 'cURL:                     ' . ( function_exists( 'curl_init' ) ? 'Supported' : 'Not Supported' ) . "\n";
		$return .= 'fsockopen:                ' . ( function_exists( 'fsockopen' ) ? 'Supported' : 'Not Supported' ) . "\n";
		$return .= 'SOAP Client:              ' . ( class_exists( 'SoapClient' ) ? 'Installed' : 'Not Installed' ) . "\n";
		$return .= 'Suhosin:                  ' . ( extension_loaded( 'suhosin' ) ? 'Installed' : 'Not Installed' ) . "\n";

		// Session stuff
		$return .= "\n" . '-- Session Configuration' . "\n\n";
		$return .= 'Session:                  ' . ( isset( $_SESSION ) ? 'Enabled' : 'Disabled' ) . "\n";

		// The rest of this is only relevant is session is enabled.
		if ( isset( $_SESSION ) ) {
			$return .= 'Session Name:             ' . esc_html( ini_get( 'session.name' ) ) . "\n";
			$return .= 'Cookie Path:              ' . esc_html( ini_get( 'session.cookie_path' ) ) . "\n";
			$return .= 'Save Path:                ' . esc_html( ini_get( 'session.save_path' ) ) . "\n";
			$return .= 'Use Cookies:              ' . ( ini_get( 'session.use_cookies' ) ? 'On' : 'Off' ) . "\n";
			$return .= 'Use Only Cookies:         ' . ( ini_get( 'session.use_only_cookies' ) ? 'On' : 'Off' ) . "\n";
		}

		$return .= "\n" . '### End System Info ###';

		return $return;
	}
}
new WPForms_Settings;
