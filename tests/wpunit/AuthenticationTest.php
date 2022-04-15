<?php
class _testAuth extends \WPGraphQL\JWT_Authentication\Auth {
	public static function _getSignedToken ( $user ) {
		return self::get_signed_token( $user );
	}
	public static function _getToken( $user ) {
		return self::get_token( $user, false );
	}
}

class AuthenticationTest extends \Codeception\TestCase\WPTestCase {

	public $admin;
	public $login_mutation;
	public $admin_username;
	public $admin_password;

	/**
	 * This function is run before each method
	 * @since 0.0.5
	 */
	public function setUp(): void {


		add_filter( 'graphql_debug_enabled', '__return_true' );
		add_filter( 'graphql_jwt_auth_secret_key', function() {
			return 'your-secret-token';
		});

		parent::setUp();

		$this->admin_password = 'testPassword';
		$this->admin_username = 'testuser';

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
			'user_login' => $this->admin_username,
			'user_pass' => $this->admin_password,
		] );


		$this->login_mutation = '
		mutation LoginUser( $input:LoginInput! ){
			login( input:$input ) {
				authToken
				user {
					username
					pages{
						edges{
							node{
								title
								content
								author {
								    node {
								        databaseId
								    }
								}
							}
						}
					}
				}
			}
		}';


	}

	/**
	 * Runs after each method.
	 * @since 0.0.5
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test logging in with a bad password to make sure we get an error returned
	 */
	public function testLoginWithBadCredentials() {

		/**
		 * Run the GraphQL query
		 */
		$actual = graphql( [
			'query' => $this->login_mutation,
			'variables' => [
				'input' => [
					'username' => $this->admin_username,
					'password' => 'badPassword',
					'clientMutationId' => uniqid( '', true ),
				]
			]
		]);

		/**
		 * Assert that a bad password will throw an error
		 */
		$this->assertArrayHasKey( 'errors', $actual );

	}

	/**
	 * testPageNodeQuery
	 * @since 0.0.5
	 */
	public function testLoginWithPage() {

		/**
		 * Set up the $args
		 */
		$args = array(
			'post_status'  => 'publish',
			'post_content' => 'Test page content',
			'post_title'   => 'Test Page Title',
			'post_type'    => 'page',
			'post_author'  => $this->admin,
		);

		/**
		 * Create the page
		 */
		$page_id = $this->factory()->post->create( $args );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'page', $page_id );

		/**
		 * Run the GraphQL query
		 */
		$actual = graphql( [
			'query' => $this->login_mutation,
			'variables' => [
				'input' => [
					'username' => $this->admin_username,
					'password' => $this->admin_password,
				]
			]
		]);

		codecept_debug( $actual );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected_user = [
			'username' => $this->admin_username,
			'pages' => [
				'edges' => [
					[
						'node' => [
							'title' => 'Test Page Title',
							'content' => apply_filters( 'the_content', $args['post_content'] ),
							'author' => [
								'node' => [
									'databaseId' => $this->admin
								],
							],
						],
					],
				],
			],
		];

		$token = $actual['data']['login']['authToken'];
		$this->assertNotEmpty( $token );
		$this->assertEquals( $expected_user, $actual['data']['login']['user'] );

	}

	public function testLoginWithNoSecretKeyConfigured() {

		/**
		 * Set the secret key to be empty
		 * which should throw an error
		 */
		add_filter( 'graphql_jwt_auth_secret_key', function() {
			return null;
		} );

		/**
		 * Run the GraphQL query
		 */
		$actual = graphql( [
			'query' => $this->login_mutation,
			'variables' => [
				'input' => [
					'username' => $this->admin_username,
					'password' => $this->admin_password,
					'clientMutationId' => uniqid( '', true ),
				]
			]
		] );

		/**
		 * Assert that a bad password will throw an error
		 */
		$this->assertArrayHasKey( 'errors', $actual );

	}

	public function filter_authentication () {
		return 'goo';
	}

	public function testLoginWithValidUserThatWasJustDeleted() {

		/**
		 * Filter the authentication to make sure it returns an error
		 */
		add_filter( 'authenticate', [ $this, 'filter_authentication'], 9999 );

		/**
		 * Run the GraphQL query
		 */
		$actual = graphql( [
			'query' => $this->login_mutation,
			'variables' => [
				'input' => [
					'username' => $this->admin_username,
					'password' => $this->admin_password,
					'clientMutationId' => uniqid( '', true ),
				]
			]
		]);

		/**
		 * Assert that a bad password will throw an error
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		remove_filter( 'authenticate', [ $this, 'filter_authentication'], 9999 );

	}

	public function testNonAuthenticatedRequest() {

		$user = \WPGraphQL\JWT_Authentication\Auth::filter_determine_current_user( 0 );
		$this->assertEquals( 0, $user );

	}

	public function testAuthenticatedRequestWithBadToken() {

		add_filter( 'graphql_jwt_auth_get_auth_header', function() {
			return 'Bearer BadToken';
		} );

		$user = \WPGraphQL\JWT_Authentication\Auth::filter_determine_current_user( 0 );
		$this->assertEquals( 0, $user );

	}

	public function testAuthenticatedRequestWithValidToken() {

		$test_user = new WP_User( $this->admin );
		$token = _testAuth::_getToken( $test_user );


		add_filter( 'graphql_jwt_auth_get_auth_header', function() use ( $token ) {
			return 'Bearer ' . $token;
		} );

		$user = \WPGraphQL\JWT_Authentication\Auth::filter_determine_current_user( 0 );

		$this->assertEquals( $this->admin, $user );

	}

	public function testRequestWithNoToken() {

		wp_set_current_user( 0 );
		add_filter( 'graphql_jwt_auth_get_auth_header', function() {
			return null;
		} );

		$user = \WPGraphQL\JWT_Authentication\Auth::filter_determine_current_user( 0 );
		$this->assertEquals( 0, $user );

	}

	public function testRequestWithInvalidToken() {

		wp_set_current_user( $this->admin );

		add_filter( 'graphql_jwt_auth_token_before_sign', function( $token ) {
			$token['iss'] = null;
			return $token;
		} );

		$test_user = new WP_User( $this->admin );
		$token = _testAuth::_getSignedToken( $test_user );

		add_filter( 'graphql_jwt_auth_get_auth_header', function() use ( $token ) {
			return 'Bearer ' . $token;
		} );

		codecept_debug( [ 'invalidToken' => $token ]);

		/**
		 * Validate the token (should not work because we filtered the iss to make it invalid)
		 */
		$token = \WPGraphQL\JWT_Authentication\Auth::validate_token( $token );

		codecept_debug( $token );

		/**
		 * Validate token should return nothing if it can't be validated properly
		 */
		$this->assertTrue( is_wp_error( $token ) );

	}

	/**
	 * If the secret key is empty we should get an exception
	 */
	public function testNoSecretKey() {

//		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer goo';

		/**
		 * Filter the secret key to return null, which should cause an exception to be thrown
		 */
		add_filter( 'graphql_jwt_auth_secret_key', function() {
			return null;
		} );


		/**
		 * Run the function to determine the current user
		 */
		$user = \WPGraphQL\JWT_Authentication\Auth::filter_determine_current_user( 0 );

		codecept_debug( [ 'user' => $user ] );

		$actual = graphql([
			'query' => $this->login_mutation,
			'variables' => [
				'input' => [
					'username' => $this->admin_username,
					'password' => $this->admin_password,
				]
			]
		]);

		codecept_debug( $actual );

		/**
		 * Ensure that the Exception prevented any user from being authenticated
		 */
		$this->assertNull( $actual['data']['login'] );
		$this->assertArrayHasKey( 'errors', $actual );

	}

}
