<?php

class AuthenticationTest extends WP_UnitTestCase {

	public $admin;

	/**
	 * This function is run before each method
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();
		$this->admin = $this->factory->user->create( [
			'role' => 'admin',
			'user_login' => 'testUser',
			'user_pass' => 'testPassword',
		] );
	}

	/**
	 * Runs after each method.
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
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
		$page_id = $this->factory->post->create( $args );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'page', $page_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = '
		mutation { 
			login(username: "testUser", password: "testPassword" ) {  
				user {
					username
					pages{
						edges{
							node{
								id
								title
								content
							}
						}
					}
				}
			} 
		}';

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		var_dump( $actual['data']['login']['user']['pages']['edges']['node'] );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'login' => [
					'user' => [
						'username' => 'testUser',
						'pages' => [
							'edges' => [
								'node' => [
									'id' => $global_id,
									'title' => 'Test Page Title',
									'content' => apply_filters( 'the_content', 'page content' ),
								]
							]
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

}