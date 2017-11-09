<?php

namespace WPGraphQL\JWT_Authentication;

use Firebase\JWT\JWT;
use GraphQL\Error\UserError;
use WPGraphQL\Data\DataSource;

class Auth {

	/**
	 * This returns the secret key, using the defined constant if defined, and passing it through a filter to
	 * allow for the config to be able to be set via another method other than a defined constant, such as an
	 * admin UI that allows the key to be updated/changed/revoked at any time without touching server files
	 *
	 * @return mixed|null|string
	 * @since 0.0.1
	 */
	public static function get_secret_key() {

		// Use the defined secret key, if it exists, otherwise use the SECURE_AUTH_SALT if it exists
		// @see: https://api.wordpress.org/secret-key/1.1/salt/
		$salt       = defined( SECURE_AUTH_SALT ) ? SECURE_AUTH_SALT : null;
		$secret_key = defined( 'GRAPHQL_JWT_AUTH_SECRET_KEY' ) ? GRAPHQL_JWT_AUTH_SECRET_KEY : $salt;

		return apply_filters( 'graphql_jwt_auth_secret_key', $secret_key );

	}

	/**
	 * Get the user and password in the request body and generate a JWT
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @return mixed
	 * @throws \Exception
	 * @since 0.0.1
	 */
	public static function login_and_get_token( $username, $password ) {

		/**
		 * First thing, check the secret key if not exist return a error
		 */
		if ( empty( self::get_secret_key() ) ) {
			throw new UserError( __( 'JWT Auth is not configured correctly. Please contact a site administrator.', 'wp-graphql-jwt-authentication' ) );
		}

		/**
		 * Authenticate the user and get the Authenticated user object in response
		 */
		$user = self::authenticate_user( $username, $password );

		/**
		 * Set the current user to the authenticated user
		 */
		if ( empty( $user->data->ID ) ) {
			throw new UserError( __( 'The user could not be found', 'wp-graphql-jwt-authentication' ) );
		}

		/**
		 * Set the current user as the authenticated user
		 */
		wp_set_current_user( $user->data->ID );

		/**
		 * The token is signed, now create the object with basic user data to send to the client
		 */
		$response = [
			'authToken' => self::get_signed_token( $user ),
			'user'      => DataSource::resolve_user( $user->data->ID ),
		];

		/**
		 * Let the user modify the data before send it back
		 */
		return ! empty( $response ) ? $response : [];
	}

	/**
	 * @param $user
	 *
	 * @return null|string
	 */
	protected static function get_signed_token( $user ) {

		/**
		 * Create a timestamp to be used in the token
		 */
		$issued = time();

		/**
		 * Determine the "not before" value for use in the token
		 *
		 * @param string   $issued_at The timestamp of the authentication, used in the token
		 * @param \WP_User $user      The authenticated user
		 */
		$not_before = apply_filters( 'graphql_jwt_auth_not_before', $issued, $user );

		/**
		 * Set the expiration time, default is 7 days.
		 */
		$expiration = $issued + ( DAY_IN_SECONDS * 7 );

		/**
		 * Determine the expiration value. Default is 7 days, but is filterable to be configured as needed
		 *
		 * @param string   $expiration The timestamp for when the token should expire
		 * @param string   $issued_at  The timestamp for when the token was issued, used for calculating the expiration
		 * @param \WP_User $user       The authenticated user
		 */
		$expiration = apply_filters( 'graphql_jwt_auth_expire', $expiration, $issued, $user );

		/**
		 * Configure the token array, which will be encoded
		 */
		$token = [
			'iss'  => get_bloginfo( 'url' ),
			'iat'  => $issued,
			'nbf'  => $not_before,
			'exp'  => $expiration,
			'data' => [
				'user' => [
					'id' => $user->data->ID,
				],
			],
		];

		/**
		 * Filter the token, allowing for individual systems to configure the token as needed
		 *
		 * @param array    $token The token array that will be encoded
		 * @param \WP_User $token The authenticated user
		 */
		$token = apply_filters( 'graphql_jwt_auth_token_before_sign', $token, $user );

		/**
		 * Encode the token
		 */
		JWT::$leeway = 60;
		$token = JWT::encode( $token, self::get_secret_key() );

		/**
		 * Return the token
		 */
		return ! empty( $token ) ? $token : null;

	}

	/**
	 * Takes a username and password and authenticates the user and returns the authenticated user object
	 *
	 * @param string $username The username for the user to login
	 * @param string $password The password for the user to login
	 *
	 * @return null|\WP_Error|\WP_User
	 */
	protected static function authenticate_user( $username, $password ) {

		/**
		 * Try to authenticate the user with the passed credentials
		 */
		$user = wp_authenticate( sanitize_user( $username ), trim( $password ) );

		/**
		 * If the authentication fails return a error
		 */
		if ( is_wp_error( $user ) ) {
			$error_code = ! empty( $user->get_error_code() ) ? $user->get_error_code() : 'invalid login';
			throw new UserError( esc_html( $error_code ) );
		}

		return ! empty( $user ) ? $user : null;

	}

	/**
	 * This is our Middleware to try to authenticate the user according to the
	 * token send.
	 *
	 * @param (int|bool) $user Logged User ID
	 *
	 * @return mixed|false|\WP_User
	 */
	public static function filter_determine_current_user( $user ) {

		/**
		 * Validate the token, which will check the Headers to see if Authentication headers were sent
		 *
		 * @since 0.0.1
		 */
		$token = Auth::validate_token();

		/**
		 * If no token was generated, return the existing value for the $user
		 */
		if ( empty( $token ) ) {

			/**
			 * Return the user that was passed in to the filter
			 */
			return $user;

		/**
		 * If there is a token
		 */
		} else {

			/**
			 * Get the current user from the token
			 */
			$user = ! empty( $token ) && ! empty( $token->data->user->id ) ? $token->data->user->id : $user;

		}

		/**
		 * Everything is ok, return the user ID stored in the token
		 */
		return $user;
	}

	/**
	 * Main validation function, this function try to get the Autentication
	 * headers and decoded.
	 *
	 * @param string $token The encoded JWT Token
	 *
	 * @throws \Exception
	 * @return mixed|boolean|string
	 */
	public static function validate_token( $token = null ) {

		/**
		 * If a token isn't passed to the method, check the Authorization Headers to see if a token was
		 * passed in the headers
		 *
		 * @since 0.0.1
		 */
		if ( empty( $token ) ) {

			/**
			 * Get the Auth header
			 */
			$auth_header = self::get_auth_header();

			/**
			 * If there's no $auth, return an error
			 *
			 * @since 0.0.1
			 */
			if ( empty( $auth_header ) ) {
				return false;
			} else {
				/**
				 * The HTTP_AUTHORIZATION is present verify the format
				 * if the format is wrong return the user.
				 */
				list( $token ) = sscanf( $auth_header, 'Bearer %s' );
			}

		}

		/**
		 * If there's no secret key, throw an error as there needs to be a secret key for Auth to work properly
		 */
		if ( ! self::get_secret_key() ) {
			throw new \Exception( __( 'JWT is not configured properly', 'wp-graphql-jwt-authentication' ) );
		}

		/**
		 * Try to decode the token
		 */
		try {

			/**
			 * Decode the Token
			 */
			JWT::$leeway = 60;
			$token = ! empty( $token ) ? JWT::decode( $token, self::get_secret_key(), [ 'HS256' ] ) : null;

			/**
			 * The Token is decoded now validate the iss
			 */
			if ( get_bloginfo( 'url' ) !== $token->iss ) {
				throw new \Exception( __( 'The iss do not match with this server', 'wp-graphql-jwt-authentication' ) );
			}

			/**
			 * So far so good, validate the user id in the token
			 */
			if ( ! isset( $token->data->user->id ) ) {
				throw new \Exception( __( 'User ID not found in the token', 'wp-graphql-jwt-authentication' ) );
			}

			/**
			 * If any exceptions are caught
			 */
		} catch ( \Exception $error ) {
			return new \WP_Error( 'invalid_token', __( 'The JWT Token is invalid', 'wp-graphql-jwt-authentication' ) );
		}

		return $token;

	}

	/**
	 * Get the value of the Authorization header from the $_SERVER super global
	 *
	 * @return mixed|string
	 */
	protected static function get_auth_header() {

		/**
		 * Looking for the HTTP_AUTHORIZATION header, if not present just
		 * return the user.
		 */
		$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : false;

		/**
		 * Double check for different auth header string (server dependent)
		 */
		$redirect_auth_header = isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : false;

		/**
		 * If the $auth header is set, use it. Otherwise attempt to use the $redirect_auth header
		 */
		$auth_header = isset( $auth_header ) ? $auth_header : ( isset( $redirect_auth_header ) ? $redirect_auth_header : null );

		/**
		 * Return the auth header, pass through a filter
		 *
		 * @param string $auth_header The header used to authenticate a user's HTTP request
		 */
		return apply_filters( 'graphql_jwt_auth_get_auth_header', $auth_header );

	}

}