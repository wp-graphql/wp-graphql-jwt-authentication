<?php
namespace WPGraphQL\JWT_Authentication;

use GraphQL\Error\UserError;
use WPGraphQL\Types;

class ManageTokens {

	/**
	 * Initialize the funcionality for managing tokens
	 */
	public static function init() {

		/**
		 * Filter the User type to have a jwtUserSecret and jwtAuthToken field
		 */
		add_filter( 'graphql_user_fields', [
			'\WPGraphQL\JWT_Authentication\ManageTokens',
			'add_user_fields'
		] );

		/**
		 * Add fields to the input for user mutations
		 */
		add_filter( 'graphql_user_mutation_input_fields', [
			'\WPGraphQL\JWT_Authentication\ManageTokens',
			'add_user_mutation_input_fields'
		] );

		add_action( 'graphql_user_object_mutation_update_additional_data', [
			'\WPGraphQL\JWT_Authentication\ManageTokens',
			'update_jwt_fields_during_mutation'
		], 10, 3 );

		/**
		 * Filter the signed token, preventing it from returning if the user has had their JWT Secret revoked
		 */
		add_filter( 'graphql_jwt_auth_signed_token', [
			'\WPGraphQL\JWT_Authentication\ManageTokens',
			'prevent_token_from_returning_if_revoked'
		], 10, 2 );

		/**
		 * Filter the expiration to use the user's expiration if a custom expiration has been set.
		 */
		add_filter( 'graphql_jwt_auth_expire', [
			'\WPGraphQL\JWT_Authentication\ManageTokens',
			'use_custom_user_expiration'
		] );

		add_filter( 'graphql_response_headers_to_send', [
			'\WPGraphQL\JWT_Authentication\ManageTokens',
			'add_tokens_to_graphql_response_headers'
		] );
	}

	/**
	 * Filters the User type in the GraphQL Schema to provide fields for querying for user's jwtAuthToken and jwtUserSecret
	 *
	 * @param array $fields The fields for the User type in the GraphQL Schema
	 *
	 * @return array $fields
	 */
	public static function add_user_fields( $fields ) {

		$fields['jwtAuthToken'] = [
			'type' => Types::string(),
			'description' => __( 'A JWT token that can be used in future requests for authentication/authorization', 'wp-graphql-jwt-authentication' ),
			'resolve' => function( \WP_User $user ) {

				/**
				 * Get the token for the user
				 */
				$token = Auth::get_token( $user );

				/**
				 * If the token cannot be returned, throw an error
				 */
				if ( empty( $token ) || is_wp_error( $token ) ) {
					throw new UserError( __( 'The JWT token could not be returned', 'wp-graphql-jwt-authentication' ) );
				}

				return ! empty( $token ) ? $token : null;
			},
		];

		$fields['jwtRefreshToken'] = [
			'type' => Types::string(),
			'description' => __( 'A JWT token that can be used in future requests to get a refreshed jwtAuthToken. If the refresh token used in a request is revoked or otherwise invalid, a valid Auth token will NOT be issued in the response headers.', 'wp-graphql-jwt-authentication' ),
			'resolve' => function( \WP_User $user ) {

				/**
				 * Get the token for the user
				 */
				$token = Auth::get_refresh_token( $user );

				/**
				 * If the token cannot be returned, throw an error
				 */
				if ( empty( $token ) || is_wp_error( $token ) ) {
					throw new UserError( __( 'The JWT token could not be returned', 'wp-graphql-jwt-authentication' ) );
				}

				return ! empty( $token ) ? $token : null;
			},
		];

		$fields['jwtUserSecret'] = [
			'type' => Types::string(),
			'description' => __( 'A unique secret tied to the users JWT token that can be revoked or refreshed. Revoking the secret prevents JWT tokens from being issued to the user. Refreshing the token invalidates previously issued tokens, but allows new tokens to be issued.', 'wp-graphql' ),
			'resolve' => function( \WP_User $user ) {

				/**
				 * Get the user's JWT Secret
				 */
				$secret = Auth::get_user_jwt_secret( $user->ID );

				/**
				 * If the secret cannot be returned, throw an error
				 */
				if ( is_wp_error( $secret ) ) {
					throw new UserError( __( 'The user secret could not be returned', 'wp-graphql-jwt-authentication' ) );
				}

				/**
				 * Return the secret
				 */
				return ! empty( $secret ) ? $secret : null;
			}
		];

		$fields['jwtAuthExpiration'] = [
			'type' => Types::string(),
			'description' => __( 'The expiration for the JWT Token for the user. If not set custom for the user, it will use the default sitewide expiration setting', 'wp-graphql-jwt-authentication' ),
			'resolve' => function( \WP_User $user ) {
				$expiration = Auth::get_token_expiration();
				return ! empty( $expiration ) ? $expiration : null;
			}
		];

		$fields['isJwtAuthSecretRevoked'] = [
			'type' => Types::non_null( Types::boolean() ),
			'description' => __( 'Whether the JWT User secret has been revoked. If the secret has been revoked, auth tokens will not be issued until an admin, or user with proper capabilities re-issues a secret for the user.', 'wp-graphql-jwt-authentication' ),
			'resolve' => function( \WP_User $user ) {
				$revoked = Auth::is_jwt_secret_revoked( $user->ID );
				return true == $revoked ? true : false;
			}
		];



		return $fields;

	}

	/**
	 * Given an array of fields, this returns an array with the new fields added
	 * @param array $fields The input fields for user mutations
	 *
	 * @return array
	 */
	public static function add_user_mutation_input_fields( array $fields ) {

		$fields['revokeJwtUserSecret'] = [
			'type' => Types::boolean(),
			'description' => __( 'If true, this will revoke the users JWT secret. If false, this will unrevoke the JWT secret AND issue a new one. To revoke, the user must have proper capabilities to edit users JWT secrets.', 'wp-graphql-jwt-authentication' ),
		];

		$fields['refreshJwtUserSecret'] = [
			'type' => Types::boolean(),
			'description' => __( 'If true, this will refresh the users JWT secret.' ),
		];

		return $fields;
	}

	/**
	 * @param int $user_id The ID of the user being mutated
	 * @param array $input The input args of the GraphQL mutation request
	 * @param string $mutation_name The name of the mutation
	 */
	public static function update_jwt_fields_during_mutation( $user_id, array $input, $mutation_name ) {

		/**
		 * If there was input to revokeJwtUserSecret, check the value for true or false, and
		 * revoke or unRevoke the token accordingly
		 */
		if ( isset( $input['revokeJwtUserSecret'] ) ) {
			if ( true === $input['revokeJwtUserSecret'] ) {
				Auth::revoke_user_secret( $user_id );
			} elseif ( false === $input['revokeJwtUserSecret'] ) {
				Auth::unrevoke_user_secret( $user_id );
			}
		}

		/**
		 * If refreshJwtUserSecret is true.
		 */
		if ( isset( $input['refreshJwtUserSecret'] ) ) {
			if ( true === $input['refreshJwtUserSecret'] ) {
				Auth::issue_new_user_secret( $user_id );
			}
		}

	}

	/**
	 * This filters the token to prevent it from being issued if it has been revoked.
	 *
	 * @param string $token
	 * @param int $user_id
	 *
	 * @return string $token
	 */
	public static function prevent_token_from_returning_if_revoked( $token, $user_id  ) {

		/**
		 * Check to see if the user's auth secret has been revoked.
		 */
		$revoked = Auth::is_jwt_secret_revoked( $user_id );

		/**
		 * If the token has been revoked, prevent it from being returned
		 */
		if ( true === $revoked ) {
			throw new UserError( __( 'The JWT token cannot be issued for this user', 'wp-graphql-jwt-authentication' ) );
		}

		return $token;

	}


	public static function use_custom_user_expiration( $expiration ) {

		$user = wp_get_current_user();

		/**
		 * If there is no current user set or the current user's secret has been revoked, return null
		 */
		if ( 0 === $user->ID || Auth::is_jwt_secret_revoked( $user->ID ) ) {
			return null;
		}

		/**
		 * If the user has custom expiration configured, use it
		 */
		$user_expiration = get_user_meta( $user->ID, 'graphql_jwt_custom_expiration', true );
		if ( ! empty( $user_expiration ) && is_string( $user_expiration ) ) {
			$expiration = $user_expiration;
		}

		return $expiration;

	}

	public static function add_tokens_to_graphql_response_headers( $headers ) {

		/**
		 * If there's a Refresh-Authorization token in the request headers, validate it
		 */
		$validate_refresh_header = Auth::validate_token( Auth::get_refresh_header(), true );

		/**
		 * If the refresh token in the request headers is valid, return a JWT Auth token that can be used for future requests
		 */
		if ( ! is_wp_error( $validate_refresh_header ) && ! empty( $validate_refresh_header->data->user->id ) ) {

			/**
			 * Get an auth token and refresh token to return
			 */
			$auth_token = Auth::get_token( new \WP_User( $validate_refresh_header->data->user->id ), false );

			/**
			 * If the tokens can be generated (not revoked, etc), return them
			 */
			if ( ! empty( $auth_token ) && ! is_wp_error( $auth_token ) ) {
				$headers['X-JWT-Auth'] = $auth_token;
			}

		}

		$validate_auth_header = Auth::validate_token( null, false );

		if ( ! is_wp_error( $validate_auth_header ) && ! empty( $validate_auth_header->data->user->id ) ) {

			$refresh_token = Auth::get_refresh_token( new \WP_User( $validate_auth_header->data->user->id ), false );

			if ( ! empty( $refresh_token ) && ! is_wp_error( $refresh_token ) ) {
				$headers['X-JWT-Refresh'] = $refresh_token;
			}

		}

		return $headers;

	}

}