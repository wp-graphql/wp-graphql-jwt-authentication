<?php
namespace WPGraphQL\JWT_Authentication;

use GraphQL\Error\UserError;
use WPGraphQL\JWT_Authentication;
use WPGraphQL\Router;

class Config {

	/**
	 * Store errors to display if the JWT is wrong
	 *
	 * @var \WP_Error
	 */
	private $jwt_error = null;

	/**
	 * Stores the secret key for use throughout
	 *
	 * @var mixed|null|string
	 */
	private static $secret_key;

	/**
	 * Config constructor.
	 */
	public function __construct() {

		/**
		 * Set the value of the secret key
		 */
		self::$secret_key = self::get_secret_key();

		/**
		 * Add cors support to the header if enabled
		 */
		add_action( 'graphql_process_http_request', [ $this, 'add_cors_support' ] );

		/**
		 * Determine the current user
		 */
		add_filter( 'determine_current_user', [ $this, 'determine_current_user' ], 10 );

		/**
		 * Process the graphql request
		 */
		add_filter( 'do_graphql_request', [ $this, 'do_graphql_request' ], 10, 2 );

		/**
		 * Create the Auth fields
		 */
		add_filter( 'graphql_rootMutation_fields', [ '\WPGraphQL\JWT_Authentication\Mutation\Auth', 'root_mutation_fields' ] );

	}

	/**
	 * This returns the secret key, using the defined constant if defined, and passing it through a filter to
	 * allow for the config to be able to be set via another method other than a defined constant, such as an
	 * admin UI that allows the key to be updated/changed/revoked at any time without touching server files
	 *
	 * @return mixed|null|string
	 * @since 0.0.1
	 */
	public static function get_secret_key() {

		if ( null === self::$secret_key ) {

			// Use the defined secret key, if it exists, otherwise use the SECURE_AUTH_SALT if it exists
			// @see: https://api.wordpress.org/secret-key/1.1/salt/
			$salt = defined( SECURE_AUTH_SALT ) ? SECURE_AUTH_SALT : null;
			$secret_key = defined('GRAPHQL_JWT_AUTH_SECRET_KEY') ? GRAPHQL_JWT_AUTH_SECRET_KEY : $salt;

			// Filter the secret key, allowing for admin UI's, for example, to be able to create/manage/revoke the
			// secret key as needed
			$secret_key = apply_filters( 'graphql_jwt_auth_secret_key', $secret_key );

			self::$secret_key = $secret_key;
		}

		return ! empty( self::$secret_key ) ? self::$secret_key : null;

	}

	/**
	 * If enabled, this will add "cors" support by adjusting the headers for the GraphQL request. This first
	 * checks to see if the constant is defined, then passes it through a filter, allowing for that value to be
	 * dynamic, for example an admin UI could be created that would allow for the option to be checked.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function add_cors_support() {

		/**
		 * Cors is disabled by default, but can be defined / filtered to be enabled
		 *
		 * @since 0.0.1
		 */
		$enable_cors = defined( 'GRAPHQL_JWT_AUTH_CORS_ENABLE' ) ? GRAPHQL_JWT_AUTH_CORS_ENABLE : false;
		$enable_cors = apply_filters( 'graphql_jwt_auth_enable_cors', $enable_cors );

		if ( $enable_cors ) {
			$headers = apply_filters( 'graphql_jwt_auth_cors_allow_headers', 'Access-Control-Allow-Headers, Content-Type, Authorization' );
			header( sprintf( 'Access-Control-Allow-Headers: %s', $headers ) );
		}

	}

	/**
	 * This is our Middleware to try to authenticate the user according to the
	 * token send.
	 *
	 * @param (int|bool) $user Logged User ID
	 * @return mixed
	 */
	public function determine_current_user( $user ) {

		/**
		 * if the request URI is the graphql endpoint validate the token don't do anything,
		 * this avoid double calls to the validate_token function.
		 */
		$validate_uri = strpos( $_SERVER['REQUEST_URI'], Router::$route );

		if ( false === $validate_uri ) {
			return $user;
		}

		/**
		 * Validate the token, which will check the Headers to see if Authentication headers were sent
		 * @since 0.0.1
		 */
		$token = Auth::validate_token();

		if ( is_wp_error( $token ) ) {
			if ( 'graphql_jwt_auth_no_auth_header' !== $token->get_error_code() ) {
				/**
				 * If there is a error, store it to show it later
				 */
				$this->jwt_error = $token;
				return $user;
			} else {
				return $user;
			}
		}

		/**
		 * Everything is ok, return the user ID stored in the token
		 */
		return $token->data->user->id;
	}

	/**
	 * Filter to hook the do_graphql_request, if the is an error in the request
	 * send it, if there is no error just continue with the current request.
	 * @since 0.0.1
	 */
	public function do_graphql_request() {
		if ( is_wp_error( $this->jwt_error ) ) {
			$error_code = $this->jwt_error->get_error_code();
			throw new UserError( $error_code->get_error_message( $error_code ) );
		}
	}

}
