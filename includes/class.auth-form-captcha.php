<?php

namespace WYSC;

use WP_Error;
use WP_User;

class AuthFormCaptcha {

	/**
	 * Construct
	 */
	public function __construct() {
		add_action( 'login_enqueue_scripts', function () {
			wp_register_script( 'wysc_script', 'https://captcha-api.yandex.ru/captcha.js', [], WYSC_PLUGIN_VERSION, true );
		} );

		// For simple login form via wp_login_form()
		add_filter( 'login_form_middle', [ $this, 'extend_fields' ] );
		add_action( 'login_form', [ $this, 'extend_fields' ] );

		if ( ! is_user_logged_in() ) {
			add_filter( 'authenticate', [ $this, 'check_captcha' ], 999, 2 );
		}

	}

	/**
	 * Add captcha script and container
	 *
	 * @return string|void
	 */
	public function extend_fields() {
		$client_key = Plugin::getOption( 'client_token' );

		wp_enqueue_script( 'wysc_script' );

		$output = wp_kses_post( '<div
			  style="height: 98px; min-width: 200px; margin: 0 0 16px 0;"
			  id="captcha-container"
			  class="smart-captcha"
			  data-sitekey="' . esc_attr( $client_key ) . '"
			  ></div>' );

		if ( doing_action( 'login_form_middle' ) ) {
			return $output;
		} else {
			echo wp_kses_post( $output );
		}
	}

	/**
	 * @param WP_User $user - User object.
	 * @param string $username - Login username.
	 *
	 * @return WP_User|WP_Error - Always return the user, WP Error otherwise.
	 */
	public function check_captcha( $user, $username ) {

		if ( ! $username ) {
			return $user;
		}

		// Bail if a rest request.
		if ( $this->is_rest_request() ) {
			return $user;
		}

		// Bail if a rest request.
		if ( str_contains( $_SERVER['REQUEST_URI'], 'xmlrpc.php' ) ) {
			return $user;
		}

		if ( isset( $_POST['smart-token'] ) ) {
			if ( wysc_check_smart_captcha( $_POST['smart-token'] ) ) {
				return $user;
			}
		}

		return new WP_Error( 'wysc_spam_check_failed', esc_html__( 'Ошибка авторизации. Введите капчу', 'smart-captcha-yandex' ) );
	}

	/**
	 * Checks if the current authentication request is RESTy or a custom URL where it should not load.
	 *
	 * @return boolean - Was a rest request?
	 */
	public function is_rest_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST || isset( $_GET['rest_route'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ), '/', 0 ) === 0 ) {
			return true;
		}

		global $wp_rewrite;
		if ( null === $wp_rewrite ) {
			$wp_rewrite = new \WP_Rewrite();
		}

		$rest_url    = wp_parse_url( trailingslashit( rest_url() ) );
		$current_url = wp_parse_url( add_query_arg( [] ) );
		$is_rest     = strpos( $current_url['path'], $rest_url['path'], 0 ) === 0;

		return $is_rest;
	}
}
