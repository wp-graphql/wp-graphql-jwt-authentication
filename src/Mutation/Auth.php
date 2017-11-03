<?php
namespace WPGraphQL\JWT_Authentication\Mutation;

use GraphQL\Error\UserError;
use WPGraphQL\JWT_Authentication\Type\Login;
use WPGraphQL\Type\WPInputObjectType;

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
				'input' => [
					'type' => new WPInputObjectType([
						'name' => 'LoginInput',
						'fields' => [
							'username' => [
								'type' => \WPGraphQL\Types::string(),
							],
							'password' => [
								'type' => \WPGraphQL\Types::string(),
							],
						]
					])
				]
			],
			'type' => new Login(),
			'resolve' => function( $source, array $args, \WPGraphQL\AppContext $context, \GraphQL\Type\Definition\ResolveInfo $info ) {

				$token = \WPGraphQL\JWT_Authentication\Auth::generate_token( sanitize_user( $args['input']['username'] ), trim( $args['input']['password'] ) );

				if ( is_wp_error( $token ) ) {
					throw new UserError( __( 'JWT is not configurated properly, please contact the admin', 'wp-graphql' ) );
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
				$token = \WPGraphQL\JWT_Authentication\Auth::validate_token( $args['token'] );

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
