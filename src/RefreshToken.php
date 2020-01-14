<?php
/**
 * Registers the "refreshToken" mutation to the WPGraphQL Schema.
 *
 * @package WPGraphQL\JWT_Authentication
 * @since 0.0.1
 */

namespace WPGraphQL\JWT_Authentication;

use GraphQL\Error\UserError;

/**
 * Class - RefreshToken
 */
class RefreshToken {
	/**
	 * Registers the mutation.
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'refreshJwtAuthToken',
			[
				'description'         => __( 'Use a valid JWT Refresh token to retrieve a new JWT Auth Token', 'wp-graphql-jwt-authentication' ),
				'inputFields'         => [
					'jwtRefreshToken' => [
						'type'        => [ 'non_null' => 'String' ],
						'description' => __( 'A valid, previously issued JWT refresh token. If valid a new Auth token will be provided. If invalid, expired, revoked or otherwise invalid, a new AuthToken will not be provided.', 'wp-graphql-jwt-authentication' ),
					],
				],
				'outputFields'        => [
					'authToken' => [
						'type'        => 'String',
						'description' => __( 'JWT Token that can be used in future requests for Authentication', 'wp-graphql-jwt-authentication' ),
					],
				],
				'mutateAndGetPayload' => function( $input ) {
					$refresh_token = ! empty( $input['jwtRefreshToken'] ) ? Auth::validate_token( $input['jwtRefreshToken'] ) : null;

					$id = isset( $refresh_token->data->user->id ) || 0 === $refresh_token->data->user->id ? absint( $refresh_token->data->user->id ) : 0;
					if ( empty( $id ) ) {
						throw new UserError( __( 'The provided refresh token is invalid', 'wp-graphql-jwt-authentication' ) );
					}

					$user = new \WP_User( $id );
					$auth_token = Auth::get_token( $user, false );

					return [ 'authToken' => $auth_token ];
				},
			]
		);
	}
}
