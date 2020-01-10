<?php
/**
 * Defines functionality for managing JWTs.
 *
 * @package WPGraphQL\JWT_Authentication
 * @since 0.0.1
 */

namespace WPGraphQL\JWT_Authentication;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\Type;
use WPGraphQL\Model\User;
use WPGraphQL\Types;

/**
 * Class - ManageToken
 */
class ManageTokens {
	/**
	 * Initialize the funcionality for managing tokens
	 */
	public static function init() {

		// Filter the User type to have a jwtUserSecret and jwtAuthToken field.
		add_filter( 'graphql_user_fields', [ __CLASS__, 'add_user_fields' ], 10, 3 );

		// Add fields to the input for user mutations.
		add_filter( 'graphql_user_mutation_input_fields', [ __CLASS__, 'add_user_mutation_input_fields' ] );
		add_action( 'graphql_user_object_mutation_update_additional_data', [ __CLASS__, 'update_jwt_fields_during_mutation' ], 10, 3 );

		// Filter the signed token, preventing it from returning if the user has had their JWT Secret revoked.
		add_filter( 'graphql_jwt_auth_signed_token', [ __CLASS__, 'prevent_token_from_returning_if_revoked' ], 10, 2 );

		// Filter the GraphQL response headers.
		add_filter( 'graphql_response_headers_to_send', [ __CLASS__, 'add_tokens_to_graphql_response_headers' ] );
		add_filter( 'graphql_response_headers_to_send', [ __CLASS__, 'add_auth_headers_to_response' ] );

		/**
		 * Add Auth Headers to REST REQUEST responses
		 *
		 * This allows clients to use WPGraphQL JWT Authentication
		 * tokens with WPGraphQL _and_ with REST API requests, and
		 * this exposes refresh tokens in the REST API response
		 * so folks can refresh their tokens after each REST API
		 * request.
		 */
		add_filter( 'rest_request_after_callbacks', [ __CLASS__, 'add_auth_headers_to_rest_response' ], 10, 3 );

		// Filter allowed HTTP headers.
		add_filter( 'graphql_access_control_allow_headers', [ __CLASS__, 'add_jwt_allowed_headers' ] );
	}

	/**
	 * Filters the User type in the GraphQL Schema to provide fields for querying for user's
	 * jwtAuthToken and jwtUserSecret
	 *
	 * @param array                            $fields         The fields for the User type in the GraphQL Schema.
	 * @param \WPGraphQL\Type\WPObjectType     $object         The WPObjectType the fields are be added to.
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry  TypeRegistry instance.
	 *
	 * @throws UserError  Invalid token/Token not found.
	 * @return array $fields
	 */
	public static function add_user_fields( $fields, $object, $type_registry ) {
		$fields['jwtAuthToken'] = [
			'type'        => $type_registry->get_type( 'String' ),
			'description' => __( 'A JWT token that can be used in future requests for authentication/authorization', 'wp-graphql-jwt-authentication' ),
			'resolve'     => function ( User $user ) {
				$user = get_user_by( 'id', $user->ID );

				// Get the token for the user.
				$token = Auth::get_token( $user );

				// If the token cannot be returned, throw an error.
				if ( empty( $token ) || is_wp_error( $token ) ) {
					throw new UserError( __( 'The JWT token could not be returned', 'wp-graphql-jwt-authentication' ) );
				}

				return ! empty( $token ) ? $token : null;
			},
		];

		$fields['jwtRefreshToken'] = [
			'type'        => $type_registry->get_type( 'String' ),
			'description' => __( 'A JWT token that can be used in future requests to get a refreshed jwtAuthToken. If the refresh token used in a request is revoked or otherwise invalid, a valid Auth token will NOT be issued in the response headers.', 'wp-graphql-jwt-authentication' ),
			'resolve'     => function ( User $user ) {
				$user = get_user_by( 'id', $user->ID );

				// Get the token for the user.
				$token = Auth::get_refresh_token( $user );

				// If the token cannot be returned, throw an error.
				if ( empty( $token ) || is_wp_error( $token ) ) {
					throw new UserError( __( 'The JWT token could not be returned', 'wp-graphql-jwt-authentication' ) );
				}

				return ! empty( $token ) ? $token : null;
			},
		];

		$fields['jwtUserSecret'] = [
			'type'        => $type_registry->get_type( 'String' ),
			'description' => __( 'A unique secret tied to the users JWT token that can be revoked or refreshed. Revoking the secret prevents JWT tokens from being issued to the user. Refreshing the token invalidates previously issued tokens, but allows new tokens to be issued.', 'wp-graphql' ),
			'resolve'     => function ( User $user ) {
				// Get the user's JWT Secret.
				$secret = Auth::get_user_jwt_secret( $user->ID );

				// If the secret cannot be returned, throw an error.
				if ( is_wp_error( $secret ) ) {
					throw new UserError( __( 'The user secret could not be returned', 'wp-graphql-jwt-authentication' ) );
				}

				// Return the secret.
				return ! empty( $secret ) ? $secret : null;
			},
		];

		$fields['jwtAuthExpiration'] = [
			'type'        => $type_registry->get_type( 'String' ),
			'description' => __( 'The expiration for the JWT Token for the user. If not set custom for the user, it will use the default sitewide expiration setting', 'wp-graphql-jwt-authentication' ),
			'resolve'     => function () {
				$expiration = Auth::get_token_expiration();

				return ! empty( $expiration ) ? $expiration : null;
			},
		];

		$fields['isJwtAuthSecretRevoked'] = [
			'type'        => $type_registry->non_null( $type_registry->get_type( 'Boolean' ) ),
			'description' => __( 'Whether the JWT User secret has been revoked. If the secret has been revoked, auth tokens will not be issued until an admin, or user with proper capabilities re-issues a secret for the user.', 'wp-graphql-jwt-authentication' ),
			'resolve'     => function ( User $user ) {
				$revoked = Auth::is_jwt_secret_revoked( $user->ID );

				return true === $revoked ? true : false;
			},
		];

		return $fields;

	}

	/**
	 * Given an array of fields, this returns an array with the new fields added
	 *
	 * @param array $fields The input fields for user mutations.
	 *
	 * @return array
	 */
	public static function add_user_mutation_input_fields( array $fields ) {
		$fields['revokeJwtUserSecret']  = [
			'type'        => Types::boolean(),
			'description' => __( 'If true, this will revoke the users JWT secret. If false, this will unrevoke the JWT secret AND issue a new one. To revoke, the user must have proper capabilities to edit users JWT secrets.', 'wp-graphql-jwt-authentication' ),
		];
		$fields['refreshJwtUserSecret'] = [
			'type'        => Types::boolean(),
			'description' => __( 'If true, this will refresh the users JWT secret.' ),
		];

		return $fields;
	}

	/**
	 * Processes JWT Authentication-related parameters in User mutations.
	 *
	 * @param int    $user_id       The ID of the user being mutated.
	 * @param array  $input         The input args of the GraphQL mutation request.
	 * @param string $mutation_name The name of the mutation.
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

		// If refreshJwtUserSecret is true.
		if ( isset( $input['refreshJwtUserSecret'] ) ) {
			if ( true === $input['refreshJwtUserSecret'] ) {
				Auth::issue_new_user_secret( $user_id );
			}
		}

	}

	/**
	 * This filters the token to prevent it from being issued if it has been revoked.
	 *
	 * @param string $token    Token.
	 * @param int    $user_id  User associated with token.
	 *
	 * @throws UserError  Revoked token.
	 * @return string $token
	 */
	public static function prevent_token_from_returning_if_revoked( $token, $user_id ) {

		// Check to see if the user's auth secret has been revoked.
		$revoked = Auth::is_jwt_secret_revoked( $user_id );

		// If the token has been revoked, prevent it from being returned.
		if ( true === $revoked ) {
			throw new UserError( __( 'The JWT token cannot be issued for this user', 'wp-graphql-jwt-authentication' ) );
		}

		return $token;

	}

	/**
	 * Returns tokens in the response headers
	 *
	 * @param array $headers  GraphQL HTTP response headers.
	 *
	 * @return array
	 */
	public static function add_tokens_to_graphql_response_headers( $headers ) {
		/**
		 * If the request _is_ SSL, or GRAPHQL_DEBUG is defined, return the tokens
		 * otherwise do not return them.
		 */
		if ( ! is_ssl() && ( ! defined( 'GRAPHQL_DEBUG' ) || true !== GRAPHQL_DEBUG ) ) {
			return $headers;
		}

		// If there's a Refresh-Authorization token in the request headers, validate it.
		$validate_refresh_header = Auth::validate_token( Auth::get_refresh_header(), true );

		// If the refresh token in the request headers is valid, return a JWT Auth token that can be used for future requests.
		if ( ! is_wp_error( $validate_refresh_header ) && ! empty( $validate_refresh_header->data->user->id ) ) {

			// Get an auth token and refresh token to return.
			$auth_token = Auth::get_token( new \WP_User( $validate_refresh_header->data->user->id ), false );

			// If the tokens can be generated (not revoked, etc), return them.
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

	/**
	 * Expose X-JWT-Refresh tokens in the response headers for REST requests.
	 *
	 * This allows clients the ability to Authenticate with WPGraphQL, use the token
	 * with REST API Requests, but get new refresh tokens from the REST API Headers
	 *
	 * @param WP_HTTP_Response $response  Response object.
	 *
	 * @return \WP_HTTP_Response
	 */
	public static function add_auth_headers_to_rest_response( $response ) {
		if ( ! $response instanceof \WP_HTTP_Response ) {
			return $response;
		}

		/**
		 * If the request _is_ SSL, or GRAPHQL_DEBUG is defined, return the tokens
		 * otherwise do not return them.
		 */
		if ( ! is_ssl() && ( ! defined( 'GRAPHQL_DEBUG' ) || true !== GRAPHQL_DEBUG ) ) {
			return $response;
		}

		/**
		 * Note: The Access-Control-Expose-Headers aren't directly filterable
		 * for REST API responses, so this overrides them altogether.
		 *
		 * This isn't ideal, as any other plugin could override as well.
		 *
		 * Might need a patch to core to allow for individual filtering.
		 */
		$response->set_headers(
			[ 'Access-Control-Expose-Headers' => 'X-WP-Total, X-WP-TotalPages, X-JWT-Refresh' ]
		);

		$refresh_token = null;

		$validate_auth_header = Auth::validate_token( str_ireplace( 'Bearer ', '', Auth::get_auth_header() ), false );

		if ( ! is_wp_error( $validate_auth_header ) && ! empty( $validate_auth_header->data->user->id ) ) {

			$refresh_token = Auth::get_refresh_token( new \WP_User( $validate_auth_header->data->user->id ), false );

		}

		if ( $refresh_token ) {
			$response->set_headers( [ 'X-JWT-Refresh' => $refresh_token ] );
		}

		return $response;
	}

	/**
	 * Expose the X-JWT-Refresh tokens in the response headers. This allows
	 * folks to grab new refresh tokens from authenticated requests for subsequent use.
	 *
	 * @param array $headers The existing response headers.
	 *
	 * @return array
	 */
	public static function add_auth_headers_to_response( array $headers ) {
		$headers['Access-Control-Expose-Headers'] = 'X-JWT-Refresh';

		return $headers;
	}

	/**
	 * Expose the X-JWT-Auth and X-JWT-Refresh as allowed headers in GraphQL responses
	 *
	 * @param array $allowed_headers The existing allowed headers.
	 *
	 * @return array
	 */
	public static function add_jwt_allowed_headers( array $allowed_headers ) {
		$allowed_headers[] = 'X-JWT-Auth';
		$allowed_headers[] = 'X-JWT-Refresh';

		return $allowed_headers;
	}
}
