<?php
namespace WPGraphQL\JWT_Authentication\Type;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

class Login extends WPObjectType {

	/**
	 * Holds the type name
	 * @var string $type_name
	 */
	private static $type_name;

	/**
	 * This holds the field definitions
	 * @var array $fields
	 */
	private static $fields;

	/**
	 * UserType constructor.
	 * @since 0.0.5
	 */
	public function __construct() {

		/**
		 * Set the type_name
		 * @since 0.0.5
		 */
		self::$type_name = 'login';

		$config = [
			'name' => self::$type_name,
			'description' => __( 'The user data that is available upon successful login', 'wp-graphql' ),
			'fields' => self::fields(),
		];

		parent::__construct( $config );

	}

	/**
	 * fields
	 *
	 * This defines the fields for the UserType. The fields are passed through a filter so the shape of the schema
	 * can be modified
	 *
	 * @return array|\GraphQL\Type\Definition\FieldDefinition[]
	 * @since 0.0.5
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = function() {

				$fields = [
					'token' => [
						'type' => Types::string(),
						'description' => __( 'The authentication token for the user', 'wp-graphql-jwt-authentication' ),
					],
					'user' => [
						'type' => Types::user(),
						'description' => __( 'The authenticated user', 'wp-graphql-jwt-authentication' ),
						'resolve' => function( $auth, array $args, AppContext $context, ResolveInfo $info ) {

							if ( ! empty( $auth['user_id'] ) ) {
								$user = new \WP_User( absint( $auth['user_id'] ) );
							}

							return ! empty( $user ) ? $user : null;

						}
					]
				];

				/**
				 * This prepares the fields by sorting them and applying a filter for adjusting the schema.
				 * Because these fields are implemented via a closure the prepare_fields needs to be applied
				 * to the fields directly instead of being applied to all objects extending
				 * the WPObjectType class.
				 */
				return self::prepare_fields( $fields, self::$type_name );

			};

		}

		return self::$fields;

	}

}