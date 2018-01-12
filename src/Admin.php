<?php
namespace WPGraphQL\JWT_Authentication;

class Admin {

	/**
	 * Initialize admin functionality for the JWT Auth plugin
	 */
	public static function init() {

		/**
		 * Setup the Admin app to manage user secrets for JWT Auth.
		 */
		add_action( 'show_user_profile', [
			'\WPGraphqL\JWT_Authentication\Admin',
			'manage_user_fields'
		] );
		add_action( 'edit_user_profile', [
			'\WPGraphqL\JWT_Authentication\Admin',
			'manage_user_fields'
		] );


		/**
		 * Enqueue the React App for the User Profile page
		 */
		add_action( 'admin_enqueue_scripts', [
			'\WPGraphqL\JWT_Authentication\Admin',
			'enqueue_scripts'
		] );


	}

	/**
	 * Add a div to the user profile page to render the React app for managing the user JWT fields
	 */
	public static function manage_user_fields() {

		/**
		 * This is the div that the React app will be rendered to
		 */
		echo "<div id='wp-graphql-jwt-user-admin'></div>";

	}

	protected function get_build_dir( $app ) {
		return WPGRAPHQL_JWT_AUTHENTICATION_PLUGIN_DIR . 'client/'. $app .'/build/';
	}

	protected static function get_build_url( $app ) {
		return WPGRAPHQL_JWT_AUTHENTICATION_PLUGIN_URL . 'client/'. $app .'/build/';
	}

	/**
	 * @return array|\WP_Error
	 */
	protected static function get_manifest( $app ) {
		$file = self::get_build_dir( $app ) . 'asset-manifest.json';

		if ( file_exists( $file ) ) {
			/**
			 * Return the files contents
			 */
			$manifest = (array) json_decode( file_get_contents( $file ) );
			return $manifest;
		} else {
			/**
			 * Return a new error if the manifest is missing
			 */
			return new \WP_Error( 'wp-graphql-jwt-auth-manifest-missing', __( 'The manifest for the UserAdmin app is missing', 'wp-graphql-jwt-authentication' ) );
		}
	}

	public static function register_scripts() {

		$app = 'UserAdmin';
		wp_register_script( 'wp-graphql-jwt-user-admin', self::get_build_url( $app ) . self::get_manifest( $app )['main.js'], [], false, true );
		wp_register_style( 'wp-graphql-jwt-user-admin', self::get_build_url( $app ) . self::get_manifest( $app )['main.css'] );

	}

	public static function enqueue_scripts( $hook ) {

		/**
		 * Register scripts
		 */
		self::register_scripts();

		/**
		 * If the admin page is the user profile page
		 */
		if ( 'profile.php' === $hook ) {

			/**
			 * Enqueue the styles and scripts for the JWT User Admin
			 */
			wp_enqueue_style( 'wp-graphql-jwt-user-admin' );
			wp_enqueue_script( 'wp-graphql-jwt-user-admin' );
		}

	}

}