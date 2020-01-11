<?php
namespace WPGraphQL\JWT_Authentication;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\Types;

class RefreshToken {

	private static $mutation;

	/**
	 * Takes an array of fields from the RootMutation and returns the fields
	 * with the "login" mutation field added
	 *
	 * @param array $fields The fields in the RootMutation of the Schema
	 *
	 * @return array $fields
	 */
	public static function root_mutation_fields( $fields ) {
		$fields['refreshJwtAuthToken'] = self::mutation();

		return $fields;
	}

	public static function mutation() {

		if ( empty( self::$mutation ) ) {

			self::$mutation = Relay::mutationWithClientMutationId([
				'name' => 'RefreshJwtAuthToken',
				'isPrivate' => false,
				'description' => __( 'Use a valid JWT Refresh token to retrieve a new JWT Auth Token', 'wp-graphql-jwt-authentication' ),
				'inputFields' => [
					'jwtRefreshToken' => [
						'type' => Types::non_null( Types::string() ),
						'description' => __( 'A valid, previously issued JWT refresh token. If valid a new Auth token will be provided. If invalid, expired, revoked or otherwise invalid, a new AuthToken will not be provided.', 'wp-graphql-jwt-authentication' ),
					],
				],
				'outputFields' => [
					'authToken' => [
						'type' => Types::string(),
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

					return [
						'authToken' => $auth_token,
					];

				},
			]);

		}

		return ( ! empty( self::$mutation ) ) ? self::$mutation : null;

	}

}
