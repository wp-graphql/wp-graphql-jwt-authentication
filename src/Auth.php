<?php

namespace WPGraphQL\JWT_Authentication;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GraphQL\Error\UserError;
use WPGraphQL\Model\User;

class Auth {

	protected static $issued;
	protected static $expiration;
	protected static $is_refresh_token = false;

	/**
	 * This returns the secret key, using the defined constant if defined, and passing it through a filter to
	 * allow for the config to be able to be set via another method other than a defined constant, such as an
	 * admin UI that allows the key to be updated/changed/revoked at any time without touching server files
	 *
	 * @return mixed|null|string
	 * @since 0.0.1
	 */
	public static function get_secret_key() {

		// Use the defined secret key, if it exists
		$secret_key = defined( 'GRAPHQL_JWT_AUTH_SECRET_KEY' ) && ! empty( GRAPHQL_JWT_AUTH_SECRET_KEY ) ? GRAPHQL_JWT_AUTH_SECRET_KEY : null;
		return apply_filters( 'graphql_jwt_auth_secret_key', $secret_key );

	}

	/**
	 * Get the user and password in the request body and generate a JWT
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @return mixed
	 * @throws Exception
	 * @since 0.0.1
	 */
	public static function login_and_get_token( $username, $password ) {

		/**
		 * First thing, check the secret key if not exist return a error
		 */
		if ( empty( self::get_secret_key() ) ) {
			return new UserError( __( 'JWT Auth is not configured correctly. Please contact a site administrator.', 'wp-graphql-jwt-authentication' ) );
		}

		/**
		 * Do whatever you need before authenticating the user.
		 *
		 * @param string $username Username as sent by the user
		 * @param string $password Password as sent by the user
		 */
		do_action( 'graphql_jwt_auth_before_authenticate', $username, $password );

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
			'authToken'    => self::get_signed_token( wp_get_current_user() ),
			'refreshToken' => self::get_refresh_token( wp_get_current_user() ),
			'user'         => new User( $user ),
			'id'           => $user->data->ID,
		];

		/**
		 * Let the user modify the data before send it back
		 *
		 * @param \WP_User $user   		The authenticated user
		 * @param array    $response 	The default response
		 */
		$response = apply_filters( 'graphql_jwt_auth_after_authenticate', $response, $user );

		return ! empty( $response ) ? $response : [];
	}

	/**
	 * Get the issued time for the token
	 *
	 * @return int
	 */
	public static function get_token_issued() {
		if ( ! isset( self::$issued ) ) {
			self::$issued = time();
		}

		return self::$issued;
	}

	/**
	 * Returns the expiration for the token
	 *
	 * @return mixed|string|null
	 */
	public static function get_token_expiration() {

		if ( ! isset( self::$expiration ) ) {

			/**
			 * Set the expiration time, default is 300 seconds.
			 */
			$expiration = 300;

			/**
			 * Determine the expiration value. Default is 5 minutes, but is filterable to be configured as needed
			 *
			 * @param string $expiration The timestamp for when the token should expire
			 */
			self::$expiration = self::get_token_issued() + apply_filters( 'graphql_jwt_auth_expire', $expiration );
		}

		return ! empty( self::$expiration ) ? self::$expiration : null;
	}

	/**
	 * Retrieves validates user and retrieve signed token
	 *
	 * @param \WP_User $user  Owner of the token.
	 * @param bool $cap_check Whether to check capabilities when getting the token
	 *
	 * @return null|string|\WP_Error
	 */
	protected static function get_signed_token( $user, $cap_check = true ) {

		/**
		 * Only allow the currently signed in user access to a JWT token
		 */
		if ( true === $cap_check && get_current_user_id() !== $user->ID || 0 === $user->ID ) {
			// See https://github.com/wp-graphql/wp-graphql-jwt-authentication/issues/111
			self::set_status(400);
			return new \WP_Error( 'graphql-jwt-no-permissions', __( 'Only the user requesting a token can get a token issued for them', 'wp-graphql-jwt-authentication' ) );
		}

		/**
		 * Determine the "not before" value for use in the token
		 *
		 * @param string   $issued The timestamp of the authentication, used in the token
		 * @param \WP_User $user   The authenticated user
		 */
		$not_before = apply_filters( 'graphql_jwt_auth_not_before', self::get_token_issued(), $user );


		/**
		 * Configure the token array, which will be encoded
		 */
		$token = [
			'iss'  => get_bloginfo( 'url' ),
			'iat'  => self::get_token_issued(),
			'nbf'  => $not_before,
			'exp'  => self::get_token_expiration(),
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
		$token       = JWT::encode( $token, self::get_secret_key(), 'HS256' );

		/**
		 * Filter the token before returning it, allowing for individual systems to override what's returned.
		 *
		 * For example, if the user should not be granted a token for whatever reason, a filter could have the token return null.
		 *
		 * @param string $token   The signed JWT token that will be returned
		 * @param int    $user_id The User the JWT is associated with
		 */
		$token = apply_filters( 'graphql_jwt_auth_signed_token', $token, $user->ID );

		/**
		 * Return the token
		 */
		return ! empty( $token ) ? $token : null;

	}

	/**
	 * Given a User ID, returns the user's JWT secret
	 *
	 * @param int $user_id
	 *
	 * @return mixed|string
	 */
	public static function get_user_jwt_secret( $user_id ) {

		$is_revoked = Auth::is_jwt_secret_revoked( $user_id );

		/**
		 * If the secret has been revoked, throw an error
		 */
		if ( true === (bool) $is_revoked ) {
			return null;
		}

		/**
		 * Filter the capability that is tied to editing/viewing user JWT Auth info
		 *
		 * @param     string 'edit_users'
		 * @param int $user_id
		 */
		$capability = apply_filters( 'graphql_jwt_auth_edit_users_capability', 'edit_users', $user_id );

		/**
		 * If the request is not from the current_user or the current_user doesn't have the proper capabilities, don't return the secret
		 */
		$is_current_user = ( $user_id === get_current_user_id() ) ? true : false;
		if ( ! $is_current_user && ! current_user_can( $capability ) ) {
			return null;
		}

		/**
		 * Get the stored secret
		 */
		$secret = get_user_meta( $user_id, 'graphql_jwt_auth_secret', true );

		/**
		 * If there is no stored secret, or it's not a string
		 */
		if ( empty( $secret ) || ! is_string( $secret ) ) {
			$secret = Auth::issue_new_user_secret( $user_id );
		}

		/**
		 * Return the $secret
		 *
		 * @param string $secret  The GraphQL JWT Auth Secret associated with the user
		 * @param int    $user_id The ID of the user the secret is associated with
		 */
		return apply_filters( 'graphql_jwt_auth_user_secret', $secret, $user_id );
	}

	/**
	 * Given a User ID, issue a new JWT Auth Secret
	 *
	 * @param int $user_id The ID of the user the secret is being issued for
	 *
	 * @return string $secret The JWT User secret for the user.
	 */
	public static function issue_new_user_secret( $user_id ) {

		/**
		 * Get the current user secret
		 */
		$secret = null;

		/**
		 * If the JWT Secret is not revoked for the user, generate a new one
		 */
		if ( ! Auth::is_jwt_secret_revoked( $user_id ) ) {

			/**
			 * Generate a new one and store it
			 */
			$secret = uniqid( 'graphql_jwt_' );
			update_user_meta( $user_id, 'graphql_jwt_auth_secret', $secret );

		}

		return ! is_wp_error( $secret ) ? $secret : null;
	}

	/**
	 * Given a User, returns whether their JWT secret has been revoked or not.
	 *
	 * @param int $user_id
	 *
	 * @return bool
	 */
	public static function is_jwt_secret_revoked( $user_id ) {
		$revoked = (bool) get_user_meta( $user_id, 'graphql_jwt_auth_secret_revoked', true );

		return isset( $revoked ) && true === $revoked ? true : false;
	}

	/**
	 * Public method for getting an Auth token for a given user
	 *
	 * @param \WP_User $user The user to get the token for
	 * @param boolean $cap_check Whether to check capabilities. Default is true.
	 *
	 * @return null|string|\WP_Error
	 */
	public static function get_token( $user, $cap_check = true ) {
		return self::get_signed_token( $user, $cap_check );
	}

	/**
	 * Given a WP_User, this returns a refresh token for the user
	 * @param \WP_User $user A WP_User object
	 * @param bool $cap_check
	 *
	 * @return null|string
	 */
	public static function get_refresh_token( $user, $cap_check = true ) {

		self::$is_refresh_token = true;

		/**
		 * Filter the token signature for refresh tokens, adding the user_secret to the signature and making the
		 * expiration long lived so that the token can be used for a long time without the client having to store a new
		 * one.
		 */
		add_filter( 'graphql_jwt_auth_token_before_sign', function( $token, \WP_User $user ) {
			$secret = Auth::get_user_jwt_secret( $user->ID );

			if ( ! empty( $secret ) && ! is_wp_error( $secret ) && true === self::is_refresh_token() ) {

				/**
				 * Set the expiration date as a year from now to make the refresh token long lived, allowing the
				 * token to be valid without changing as long as it has not been revoked or otherwise invalidated,
				 * such as a refreshed user secret.
				 */
				$token['exp']                         = apply_filters( 'graphql_jwt_auth_refresh_token_expiration', ( self::get_token_issued() + ( DAY_IN_SECONDS * 365 ) ) );
				$token['data']['user']['user_secret'] = $secret;

				self::$is_refresh_token = false;

			}

			return $token;
		}, 10, 2 );

		return self::get_signed_token( $user, $cap_check );
	}

	public static function is_refresh_token() {
		return true === self::$is_refresh_token ? true : false;
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

		if ( defined( 'GRAPHQL_JWT_AUTH_SET_COOKIES' ) && ! empty( GRAPHQL_JWT_AUTH_SET_COOKIES ) && GRAPHQL_JWT_AUTH_SET_COOKIES ) {
			$credentials = [
				'user_login'  => sanitize_user( $username ),
				'user_password'  => trim( $password ),
				'remember'  => false,
			];

			 // Try to authenticate the user with the passed credentials, log him in and set cookies
			$user = wp_signon( $credentials, true );
		} else {
			 // Try to authenticate the user with the passed credentials
			$user = wp_authenticate( sanitize_user( $username ), trim( $password ) );
		}

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
	 * @throws Exception
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
		if ( empty( $token ) || is_wp_error( $token ) ) {

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
		return absint( $user );
	}

	/**
	 * Given a user ID, if the ID is for a valid user and the current user has proper capabilities, this revokes
	 * the JWT Secret from the user.
	 *
	 * @param int $user_id
	 *
	 * @return mixed|boolean|\WP_Error
	 */
	public static function revoke_user_secret( $user_id ) {

		/**
		 * Filter the capability that is tied to editing/viewing user JWT Auth info
		 *
		 * @param     string 'edit_users'
		 * @param int $user_id
		 */
		$capability = apply_filters( 'graphql_jwt_auth_edit_users_capability', 'edit_users', $user_id );

		/**
		 * If the current user can edit users, or the current user is the user being edited
		 */
		if (
			0 !== get_user_by( 'id', $user_id )->ID &&
			(
				current_user_can( $capability ) ||
				$user_id === get_current_user_id()
			)
		) {

			/**
			 * Set the user meta as true, marking the secret as revoked
			 */
			update_user_meta( $user_id, 'graphql_jwt_auth_secret_revoked', 1 );

			return true;

		} else {
			// See https://github.com/wp-graphql/wp-graphql-jwt-authentication/issues/111
			self::set_status(401);
			return new \WP_Error( 'graphql-jwt-auth-cannot-revoke-secret', __( 'The JWT Auth Secret cannot be revoked for this user', 'wp-graphql-jwt-authentication' ) );

		}

	}

	/**
	 * Given a user ID, if the ID is for a valid user and the current user has proper capabilities, this unrevokes
	 * the JWT Secret from the user.
	 *
	 * @param int $user_id
	 *
	 * @return mixed|boolean|\WP_Error
	 */
	public static function unrevoke_user_secret( int $user_id ) {

		/**
		 * Filter the capability that is tied to editing/viewing user JWT Auth info
		 *
		 * @param     string 'edit_users'
		 * @param int $user_id
		 */
		$capability = apply_filters( 'graphql_jwt_auth_edit_users_capability', 'edit_users', $user_id );

		/**
		 * If the user_id is a valid user, and the current user can edit_users
		 */
		if ( 0 !== get_user_by( 'id', $user_id )->ID && current_user_can( $capability ) ) {

			/**
			 * Issue a new user secret, invalidating any that may have previously been in place, and mark the
			 * revoked meta key as false, showing that the secret has not been revoked
			 */
			Auth::issue_new_user_secret( $user_id );
			update_user_meta( $user_id, 'graphql_jwt_auth_secret_revoked', 0 );

			return true;

		} else {
			// See https://github.com/wp-graphql/wp-graphql-jwt-authentication/issues/111
			self::set_status(401);
			return new \WP_Error( 'graphql-jwt-auth-cannot-unrevoke-secret', __( 'The JWT Auth Secret cannot be unrevoked for this user', 'wp-graphql-jwt-authentication' ) );

		}

	}


	protected static function set_status( $status_code ) {
		add_filter( 'graphql_response_status_code', function() use ( $status_code ) {
			return $status_code;
		});
	}

	/**
	 * Main validation function, this function try to get the Authentication
	 * headers and decoded.
	 *
	 * @param string $token The encoded JWT Token
	 *
	 * @throws Exception
	 * @return mixed|boolean|string
	 */
	public static function validate_token( $token = null, $refresh = false ) {

		self::$is_refresh_token = ( true === $refresh ) ? true : false;

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
				return $token;
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
			// See https://github.com/wp-graphql/wp-graphql-jwt-authentication/issues/111
			self::set_status( 403 );
			return new \WP_Error( 'invalid-secret-key', __( 'JWT is not configured properly', 'wp-graphql-jwt-authentication' ) );
		}

		/**
		 * Decode the Token
		 */
		JWT::$leeway = 60;

		try {
			$token = ! empty( $token ) ? JWT::decode( $token, new Key( self::get_secret_key(), 'HS256') ) : null;
		} catch ( Exception $exception ) {
			$token = new \WP_Error( 'invalid-secret-key', $exception->getMessage() );
		}

		/**
		 * If there's no token listed, just bail now before validating an empty token.
		 * This will treat the request as a public request
		 */
		if ( empty( $token ) || is_wp_error( $token )  ) {
			return $token;
		}

		/**
		 * Allow multiple domains to be used as token iss value
		 * This is useful if you want to make your token valid over several domains
		 * Default value is the current site url
		 * Used along with the 'graphql_jwt_auth_token_before_sign' filter
		 */

		$allowed_domains = array(get_bloginfo('url'));
		$allowed_domains = apply_filters('graphql_jwt_auth_iss_allowed_domains', $allowed_domains);

		/**
		 * The Token is decoded now validate the iss
		 */

		if ( ! isset( $token->iss ) || ! in_array( $token->iss, $allowed_domains ) ) {
			// See https://github.com/wp-graphql/wp-graphql-jwt-authentication/issues/111
			self::set_status(401);
			return new \WP_Error( 'invalid-jwt', __( 'The iss do not match with this server', 'wp-graphql-jwt-authentication' ) );
		}

		/**
		 * So far so good, validate the user id in the token
		 */
		if ( ! isset( $token->data->user->id ) ) {
			// See https://github.com/wp-graphql/wp-graphql-jwt-authentication/issues/111
			self::set_status(401);
			return new \WP_Error( 'invalid-jwt', __( 'User ID not found in the token', 'wp-graphql-jwt-authentication' ) );
		}

		/**
		 * If there is a user_secret in the token (refresh tokens) make sure it matches what
		 */
		if ( isset( $token->data->user->user_secret ) ) {

			if ( Auth::is_jwt_secret_revoked( $token->data->user->id ) ) {
				// See https://github.com/wp-graphql/wp-graphql-jwt-authentication/issues/111
				self::set_status(401);
				return new \WP_Error( 'invalid-jwt', __( 'The User Secret does not match or has been revoked for this user', 'wp-graphql-jwt-authentication' ) );
			}
		}

		if ( is_wp_error( $token ) ) {
			self::set_status( 403 );
		}

		self::$is_refresh_token = false;

		return $token;

	}

	/**
	 * Get the value of the Authorization header from the $_SERVER super global
	 *
	 * @return mixed|string
	 */
	public static function get_auth_header() {

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
		$auth_header = $auth_header !== false ? $auth_header : ( $redirect_auth_header !== false ? $redirect_auth_header : null );

		/**
		 * Return the auth header, pass through a filter
		 *
		 * @param string $auth_header The header used to authenticate a user's HTTP request
		 */
		return apply_filters( 'graphql_jwt_auth_get_auth_header', $auth_header );

	}

	public static function get_refresh_header() {

		/**
		 * Check to see if the incoming request has a "Refresh-Authorization" header
		 */
		$refresh_header = isset( $_SERVER['HTTP_REFRESH_AUTHORIZATION'] ) ? sanitize_text_field( $_SERVER['HTTP_REFRESH_AUTHORIZATION'] ) : false;

		return apply_filters( 'graphql_jwt_auth_get_refresh_header', $refresh_header );

	}

}
