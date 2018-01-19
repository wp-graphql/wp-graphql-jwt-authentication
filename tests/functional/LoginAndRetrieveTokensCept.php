<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Login with valid username and password and retrieve accessToken and refreshToken');

$username = uniqid();
$user = $I->haveUserInDatabase( $username, 'administrator', [ 'user_pass' => 'password' ] );

/**
 * Login with username and password
 */
$I->sendPOST( 'http://wp.localhost/graphql', json_encode([
	'query' => '
		mutation Login($input: LoginInput!) {
			login( input: $input ) {
				authToken
				refreshToken
				user {
				  username
				}
			}
		}',
	'variables' => [
		'input' => [
			'username' => $username,
			'password' => 'password',
			'clientMutationId' => uniqid(),
		]
	],
], true));

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();

$response = $I->grabResponse();
$response_array = json_decode( $response, true );
$I->assertArrayNotHasKey( 'errors', $response_array  );
$I->assertArrayHasKey( 'data', $response_array );
$I->assertNotEmpty( $response_array['data']['login']['authToken'] );
$I->assertNotEmpty( $response_array['data']['login']['refreshToken'] );
$I->assertNotEmpty( $response_array['data']['login']['user']['username'] );
$I->assertEquals( $username, $response_array['data']['login']['user']['username'] );