<?php
namespace WPGraphQL\JWT_Authentication\Mutation;

use WPGraphQL\JWT_Authentication\Config;
use WPGraphQL\JWT_Authentication\Type\Login;

class Auth {

	/**
	 * Add fields to the rootMutation for authentication
	 *
	 * @param $fields
	 * @return mixed
	 */
	public static function root_mutation_fields( $fields ) {

		$fields['login'] = [
			'args' => [
				'username' => [
					'type' => \WPGraphQL\Types::string(),
				],
				'password' => [
					'type' => \WPGraphQL\Types::string(),
				],
			],
			'type' => new Login(),
			'resolve' => function( $source, array $args, \WPGraphQL\AppContext $context, \GraphQL\Type\Definition\ResolveInfo $info ) {

				$token = \WPGraphQL\JWT_Authentication\Auth::generate_token( sanitize_user( $args['username'] ), trim( $args['password'] ) );

				if ( is_wp_error( $token ) ) {
					throw new \Exception( __( 'JWT is not configurated properly, please contact the admin', 'wp-graphql' ) );
				}

				return $token;
			},
		];

		$fields['authToken'] = [
			'args' => [
				'token' => [
					'type' => \WPGraphQL\Types::string(),
				]
			],
			'type' => \WPGraphQL\Types::boolean(),
			'resolve' => function( $source, array $args, \WPGraphQL\AppContext $context, \GraphQL\Type\Definition\ResolveInfo $info ) {

				// Set the default return value
				$return = false;

				/**
				 * Validate the token
				 */
				$token = Config::validate_token( $args['token'], true );

				/**
				 * If the token returns valid
				 */
				if ( $token && $token->data->user->id ) {
					wp_set_current_user( $token->data->user->id );
					$context->viewer = wp_get_current_user();
					$return = true;
				}

				return ( $return ) ? true : false;
			},
		];

		return $fields;
	}

}
