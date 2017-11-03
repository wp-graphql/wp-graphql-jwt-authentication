<?php
namespace WPGraphQL\JWT_Authentication;


use Firebase\JWT\JWT;
use GraphQL\Error\UserError;

class Auth {

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
	public static function generate_token( $username, $password ) {

		/**
		 * First thing, check the secret key if not exist return a error
		 */
		if ( ! Config::get_secret_key() ) {
			throw new UserError( __( 'JWT Auth is not configured correctly. Please contact a site administrator.', 'wp-graphql-jwt-authentication' ) );
		}

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

		/**
		 * Create a timestamp to be used in the token
		 */
		$issued = time();

		/**
		 * Set the expiration time, default is 7 days.
		 */
		$expiration = $issued + ( DAY_IN_SECONDS * 7 );

		/**
		 * Determine the "not before" value for use in the token
		 *
		 * @param string   $issued_at The timestamp of the authentication, used in the token
		 * @param \WP_User $user      The authenticated user
		 */
		$not_before = apply_filters( 'graphql_jwt_auth_not_before', $issued, $user );

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
		$token = JWT::encode( $token, Config::get_secret_key() );

		/**
		 * The token is signed, now create the object with basic user data to send to the client
		 */
		$auth_data = array(
			'token'   => $token,
			'user_id' => $user->data->ID,
		);

		/**
		 * Filter the $auth_data, allowing for individual systems to configure what data should be sent to the client
		 * upon authentication.
		 *
		 * @param array    $auth_data An array of basic data about the user, including the ecnoded token
		 * @param \WP_User $user      The authenticated user
		 */
		$auth_data = apply_filters( 'graphql_jwt_auth_token_before_dispatch', $auth_data, $user );

		/**
		 * Log the user in
		 */
		wp_set_current_user( $user->data->ID );

		/**
		 * Let the user modify the data before send it back
		 */
		return ! empty( $auth_data ) ? $auth_data : $token;
	}

	/**
	 * Main validation function, this function try to get the Autentication
	 * headers and decoded.
	 *
	 * @param string $token The encoded JWT Token
	 *
	 * @throws \Exception
	 * @return mixed
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
			 * Looking for the HTTP_AUTHORIZATION header, if not present just
			 * return the user.
			 */
			$auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : false;

			/**
			 * Double check for different auth header string (server dependent)
			 */
			if ( false === $auth ) {
				$auth = isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : false;
			}

			/**
			 * If there's no $auth, return an error
			 *
			 * @since 0.0.1
			 */
			if ( false === $auth ) {
				//throw new UserError( __( 'Authorization header not found.', 'wp-graphql-jwt-authentication' ) );
			}

			if ( $auth ) {
				/**
				 * The HTTP_AUTHORIZATION is present verify the format
				 * if the format is wrong return the user.
				 */
				list( $token ) = sscanf( $auth, 'Bearer %s' );
			}

		}

		/**
		 * If there's no secret key, throw an error as there needs to be a secret key for Auth to work properly
		 */
		if ( ! Config::get_secret_key() ) {
			throw new UserError( __( 'JWT is not configured properly', 'wp-graphql-jwt-authentication' ) );
		}

		/**
		 * Try to decode the token
		 */
		try {

			if ( empty( $token ) ) {
				return;
			}

			/**
			 * Decode the token
			 */
			$token = JWT::decode( $token, Config::get_secret_key(), array( 'HS256' ) );

			/**
			 * The Token is decoded now validate the iss
			 */
			if ( get_bloginfo( 'url' ) !== $token->iss ) {
				//throw new UserError( __( 'The iss do not match with this server', 'wp-graphql-jwt-authentication' ) );
			}
			/**
			 * So far so good, validate the user id in the token
			 */
			if ( ! isset( $token->data->user->id ) ) {
				//throw new UserError( __( 'User ID not found in the token', 'wp-graphql-jwt-authentication' ) );
			}

		} catch ( UserError $error ) {
			//throw new UserError( esc_html( $error->getMessage() ) );
		}

		return $token;

	}

}