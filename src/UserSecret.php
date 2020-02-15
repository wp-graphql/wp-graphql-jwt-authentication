<?php

namespace WPGraphQL\JWT_Authentication;

use GraphQL\Error\UserError;

/**
 * Class UserSecret
 * @package WPGraphQL\JWT_Authentication
 */
class UserSecret {
	public static function register_mutation() {
		register_graphql_mutation(
			'refreshJwtUserSecret',
			[
				'description'         => __( 'Refreshes the users JWT secret and therefore invalidates all active tokens of this user.', 'wp-graphql-jwt-authentication' ),
				'inputFields'         => [],
				'outputFields'        => [],
				'mutateAndGetPayload' => function () {

					$token = Auth::validate_token();

					$id = isset( $token->data->user->id ) || 0 === $token->data->user->id ? absint( $token->data->user->id ) : 0;
					if ( empty( $id ) ) {
						throw new UserError( __( 'The provided token is invalid', 'wp-graphql-jwt-authentication' ) );
					}

					$user_secret = Auth::issue_new_user_secret( $id );

					if ( empty( $user_secret ) ) {
						throw new UserError( __( 'User secret could not be changed.', 'wp-graphql-jwt-authentication' ) );
					}

					return [];
				},
			]
		);
	}
}
