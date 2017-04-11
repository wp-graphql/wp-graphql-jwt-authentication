<?php
/**
 * Plugin Name:     WPGraphQL JWT Authentication
 * Plugin URI:      https://www.wpgraphql.com
 * Description:     JWT Authentication for WPGraphQL
 * Author:          WPGraphQL, Jason Bahl
 * Author URI:      https://www.wpgraphql.com
 * Text Domain:     wp-graphql-jwt-authentication-jwt-authentication
 * Domain Path:     /languages
 * Version:         0.1.0
 * @package         WPGraphQL_JWT_Authentication
 */
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use \Firebase\JWT\JWT;

if ( ! class_exists( 'WPGraphQL_JWT_Authentication' ) ) :

	final class WPGraphQL_JWT_Authentication {

		/**
		 * Stores the instance of the WPGraphQL_JWT_Authentication class
		 * @var WPGraphQL_JWT_Authentication The one true WPGraphQL_JWT_Authentication
		 * @since  0.0.1
		 * @access private
		 */
		private static $instance;

		/**
		 * Store errors to display if the JWT is wrong
		 *
		 * @var \WP_Error
		 */
		private $jwt_error = null;

		/**
		 * The instance of the WPGraphQL_JWT_Authentication object
		 * @return object|WPGraphQL_JWT_Authentication - The one true WPGraphQL_JWT_Authentication
		 * @since  0.0.1
		 * @access public
		 */
		public static function instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPGraphQL_JWT_Authentication ) ) {
				self::$instance = new WPGraphQL_JWT_Authentication;
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->actions();
			}

			/**
			 * Fire off init action
			 *
			 * @param WPGraphQL_JWT_Authentication $instance The instance of the WPGraphQL_JWT_Authentication class
			 */
			do_action( 'graphql_jwt_authentication_init', self::$instance );

			/**
			 * Return the WPGraphQL_JWT_Authentication Instance
			 */
			return self::$instance;
		}

		/**
		 * Throw error on object clone.
		 * The whole idea of the singleton design pattern is that there is a single object
		 * therefore, we don't want the object to be cloned.
		 * @since  0.0.1
		 * @access public
		 * @return void
		 */
		public function __clone() {

			// Cloning instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'The WPGraphQL_JWT_Authentication class should not be cloned.', 'wp-graphql-jwt-authentication' ), '0.0.1' );

		}

		/**
		 * Disable unserializing of the class.
		 * @since  0.0.1
		 * @access protected
		 * @return void
		 */
		public function __wakeup() {

			// De-serializing instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'De-serializing instances of the WPGraphQL class is not allowed', 'wp-graphql-jwt-authentication' ), '0.0.1' );

		}

		/**
		 * Setup plugin constants.
		 * @access private
		 * @since  0.0.1
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'WPGRAPHQL_JWT_AUTHENTICATION_VERSION' ) ) {
				define( 'WPGRAPHQL_JWT_AUTHENTICATION_VERSION', '0.0.1' );
			}

			// Plugin Folder Path.
			if ( ! defined( 'WPGRAPHQL_JWT_AUTHENTICATION_PLUGIN_DIR' ) ) {
				define( 'WPGRAPHQL_JWT_AUTHENTICATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin Folder URL.
			if ( ! defined( 'WPGRAPHQL_JWT_AUTHENTICATION_PLUGIN_URL' ) ) {
				define( 'WPGRAPHQL_JWT_AUTHENTICATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin Root File.
			if ( ! defined( 'WPGRAPHQL_JWT_AUTHENTICATION_PLUGIN_FILE' ) ) {
				define( 'WPGRAPHQL_JWT_AUTHENTICATION_PLUGIN_FILE', __FILE__ );
			}

		}

		/**
		 * Include required files.
		 * Uses composer's autoload
		 * @access private
		 * @since  0.0.1
		 * @return void
		 */
		private function includes() {

			// Autoload Required Classes
			require_once( WPGRAPHQL_JWT_AUTHENTICATION_PLUGIN_DIR . 'vendor/autoload.php' );

		}

		public function actions() {

			add_action( 'graphql_process_http_request', [ $this, 'add_cors_support' ] );
			add_filter( 'determine_current_user', [ $this, 'determine_current_user' ], 10 );
			add_filter( 'do_graphql_request', [ $this, 'do_graphql_request' ], 10, 2 );
			add_filter( 'graphql_rootMutation_fields', [ $this, 'auth_field' ] );

		}

		public function auth_field( $fields ) {

			$fields['authenticate'] = [
				'args' => [
					'username' => [
						'type' => \WPGraphQL\Types::string(),
					],
					'password' => [
						'type' => \WPGraphQL\Types::string(),
					],
				],
				'type' => new \WPGraphQL\Type\WPObjectType([
					'name' => 'authenticate',
					'fields' => function () {
						return [
							'token' => [
								'type' => \WPGraphQL\Types::string(),
							],
						];
					},
				]),
				'resolve' => function( $source, array $args, \WPGraphQL\AppContext $context, \GraphQL\Type\Definition\ResolveInfo $info ) {
					$token = $this->generate_token( $args['username'], $args['password'] );
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

					/**
					 * Validate the token
					 */
					$token = $this->validate_token( $args['token'], true );

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

		public function add_cors_support() {

			/**
			 * Cors is disabled by default, but can be filtered to be enabled
			 * @since 0.0.1
			 */
			$enable_cors = apply_filters( 'graphql_jwt_auth_enable_cors', 'return__false' );

			if ( $enable_cors ) {
				$headers = apply_filters( 'graphql_jwt_auth_cors_allow_headers', 'Access-Control-Allow-Headers, Content-Type, Authorization' );
				header( sprintf( 'Access-Control-Allow-Headers: %s', $headers ) );
			}

		}

		/**
		 * Get the user and password in the request body and generate a JWT
		 *
		 * @param [type] $request [description]
		 * @return mixed
		 */
		public function generate_token( $username, $password ) {

			/**
			 * @todo: make this dynamic
			 */
			$secret_key = 'some_unique_secret_key';

			/**
			 * First thing, check the secret key if not exist return a error
			 */
			if ( ! $secret_key ) {
				return new \WP_Error(
					'graphql_jwt_auth_bad_config',
					__( 'JWT is not configurated properly, please contact the admin', 'wp-graphql-jwt-authentication' ),
					array(
						'status' => 403,
					)
				);
			}

			/**
			 * Try to authenticate the user with the passed credentials
			 */
			$user = wp_authenticate( $username, $password );

			/**
			 * If the authentication fails return a error
			 */
			if ( is_wp_error( $user ) ) {
				$error_code = $user->get_error_code();

				return new \WP_Error(
					'[graphql_jwt_auth] ' . $error_code,
					$user->get_error_message( $error_code ),
					array(
						'status' => 403,
					)
				);
			}

			/**
			 * Valid credentials, the user exists create the according Token
			 */
			$issued_at = time();
			$not_before = apply_filters( 'graphql_jwt_auth_not_before', $issued_at, $issued_at );
			$expire = apply_filters( 'graphql_jwt_auth_expire', $issued_at + ( DAY_IN_SECONDS * 7 ), $issued_at );
			$token = array(
				'iss' => get_bloginfo( 'url' ),
				'iat' => $issued_at,
				'nbf' => $not_before,
				'exp' => $expire,
				'data' => array(
					'user' => array(
						'id' => $user->data->ID,
					),
				),
			);

			/**
			 * Let the user modify the token data before the sign.
			 */
			$token = JWT::encode( apply_filters( 'graphql_jwt_auth_token_before_sign', $token, $user ), $secret_key );

			/**
			 * The token is signed, now create the object with no sensible user data to the client
			 */
			$data = array(
				'token' => $token,
				'user_email' => $user->data->user_email,
				'user_nicename' => $user->data->user_nicename,
				'user_display_name' => $user->data->display_name,
			);

			/**
			 * Let the user modify the data before send it back
			 */
			return apply_filters( 'graphql_jwt_auth_token_before_dispatch', $data, $user );
		}

		/**
		 * This is our Middleware to try to authenticate the user according to the
		 * token send.
		 *
		 * @param (int|bool) $user Logged User ID
		 * @return mixed
		 */
		public function determine_current_user( $user ) {

			/**
	         * if the request URI is the graphql endpoint validate the token don't do anything,
	         * this avoid double calls to the validate_token function.
	         */
			$validate_uri = strpos( $_SERVER['REQUEST_URI'], \WPGraphQL\Router::$endpoint );
			if ( false === $validate_uri ) {
				return $user;
			}

			/**
			 * Validate the token, which will check the Headers to see if Authentication headers were sent
			 * @since 0.0.1
			 */
			$token = $this->validate_token();

			if ( is_wp_error( $token ) ) {
				if ( 'graphql_jwt_auth_no_auth_header' !== $token->get_error_code() ) {
					/**
					 * If there is a error, store it to show it later
					 */
					$this->jwt_error = $token;
					return $user;
				} else {
					return $user;
				}
			}

			/**
			 * Everything is ok, return the user ID stored in the token
			 */
			return $token->data->user->id;
		}

		/**
		 * Main validation function, this function try to get the Autentication
		 * headers and decoded.
		 *
		 * @param bool $output
		 * @return mixed
		 */
		public function validate_token( $token = null, $output = true ) {

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
				 * @since 0.0.1
				 */
				if ( false === $auth ) {
					return new \WP_Error(
						'graphql_jwt_auth_no_auth_header',
						__( 'Authorization header not found.', 'wp-graphql-jwt-authentication' ),
						array(
							'status' => 403,
						)
					);
				}

				/**
				 * The HTTP_AUTHORIZATION is present verify the format
				 * if the format is wrong return the user.
				 */
				list( $token ) = sscanf( $auth, 'Bearer %s' );

			}

			/**
			 * If there's still no $token, return an error
			 * @since 0.0.1
			 */
			if ( empty( $token ) ) {
				return new \WP_Error(
					'graphql_jwt_auth_bad_auth_header',
					__( 'Authorization header malformed.', 'wp-graphql-jwt-authentication' ),
					array(
						'status' => 403,
					)
				);
			}

			/**
			 * Get the Secret Key
			 * @todo: make this dynamic
			 */
			$secret_key = 'some_unique_secret_key';

			if ( ! $secret_key ) {
				return new \WP_Error(
					'graphql_jwt_auth_bad_config',
					__( 'JWT is not configurated properly, please contact the admin', 'wp-graphql-jwt-authentication' ),
					array(
						'status' => 403,
					)
				);
			}
			/**
			 * Try to decode the token
			 */
			try {
				$token = JWT::decode( $token, $secret_key, array( 'HS256' ) );

				/**
				 * The Token is decoded now validate the iss
				 */
				if ( get_bloginfo( 'url' ) !== $token->iss ) {
					/**
					 * The iss do not match, return error
					 */
					return new \WP_Error(
						'graphql_jwt_auth_bad_iss',
						__( 'The iss do not match with this server', 'wp-graphql-jwt-authentication' ),
						array(
							'status' => 403,
						)
					);
				}
				/**
				 * So far so good, validate the user id in the token
				 */
				if ( ! isset( $token->data->user->id ) ) {
					/** No user id in the token, abort!! */
					return new \WP_Error(
						'graphql_jwt_auth_bad_request',
						__( 'User ID not found in the token', 'wp-graphql-jwt-authentication' ),
						array(
							'status' => 403,
						)
					);
				}
				/**
				 * Everything looks good return the decoded token if the $output is false
				 */
				if ( $output ) {
					return $token;
				}

				/**
				 * If the output is true return an answer to the request to show it
				 */
				return array(
					'code' => 'graphql_jwt_auth_valid_token',
					'data' => array(
						'status' => 200,
					),
				);
			} catch ( Exception $e ) {
				/**
				 * Something is wrong trying to decode the token, send back the error
				 */
				return new WP_Error(
					'graphql_jwt_auth_invalid_token',
					$e->getMessage(),
					array(
						'status' => 403,
					)
				);
			}
		}

		/**
		 * Filter to hook the do_graphql_request, if the is an error in the request
		 * send it, if there is no error just continue with the current request.
		 * @since 0.0.1
		 */
		public function do_graphql_request() {
			if ( is_wp_error( $this->jwt_error ) ) {
				return $this->jwt_error;
			}
		}

	}

endif;

function graphql_jwt_authentication_init() {
	return \WPGraphQL_JWT_Authentication::instance();
}

add_action( 'graphql_init', 'graphql_jwt_authentication_init' );