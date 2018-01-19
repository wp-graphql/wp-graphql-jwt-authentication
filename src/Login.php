<?php
namespace WPGraphQL\JWT_Authentication;

use GraphQLRelay\Relay;
use WPGraphQL\Types;

class Login {

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
		$fields['login'] = self::mutation();

		return $fields;
	}

	protected static function mutation() {

		if ( empty( self::$mutation ) ) {

			self::$mutation = Relay::mutationWithClientMutationId([
				'name' => 'Login',
				'isPrivate' => false,
				'description' => __( 'Login a user. Request for an authToken and User details in response', 'wp-graphql-jwt-authentication' ),
				'inputFields' => [
					'username' => [
						'type' => Types::non_null( Types::string() ),
						'description' => __( 'The username used for login. Typically a unique or email address depending on specific configuration', 'wp-graphql-jwt-authentication' ),
					],
					'password' => [
						'type' => Types::non_null( Types::string() ),
						'description' => __( 'The plain-text password for the user logging in.', 'wp-graphql-jwt-authentication' ),
					],
				],
				'outputFields' => [
					'authToken' => [
						'type' => Types::string(),
						'description' => __( 'JWT Token that can be used in future requests for Authentication', 'wp-graphql-jwt-authentication' ),
					],
					'refreshToken' => [
						'type' => Types::string(),
						'description' => __( 'A JWT token that can be used in future requests to get a refreshed jwtAuthToken. If the refresh token used in a request is revoked or otherwise invalid, a valid Auth token will NOT be issued in the response headers.', 'wp-graphql-jwt-authentication' ),
					],
					'user' => [
						'type' => Types::user(),
						'description' => __( 'The user that was logged in', 'wp-graphql-jwt-authentication' ),
					],
				],
				'mutateAndGetPayload' => function( $input ) {

					/**
					 * Login the user in and get an authToken and user in response
					 */
					return Auth::login_and_get_token( sanitize_user( $input['username'] ), trim( $input['password'] ) );

				},
			]);

		}

		return ( ! empty( self::$mutation ) ) ? self::$mutation : null;

	}

}
