<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Use an invalid Refresh token to try and get a new AuthToken via GraphQL mutation');

/**
 * Make sure we have a user to test with
 */
$username = uniqid();
$user = $I->haveUserInDatabase( $username, 'administrator', [ 'user_pass' => 'password' ] );

/**
 * Login with username and password to get the authToken for use in the subsequent Authenticated request
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
		}
	',
	'variables' => [
		'input' => [
			'username' => $username,
			'password' => 'password',
			'clientMutationId' => uniqid(),
		]
	],
], true));

$I->seeResponseCodeIs( 200 );

/**
 * Get the JSON response
 */
$response = $I->grabResponse();

/**
 * Convert the response to JSON for making assertions
 */
$response_array = json_decode( $response, true );

/**
 * Get the refresh token from the response.
 */
$refreshToken = $response_array['data']['login']['refreshToken'];

/**
 * Update user meta to revoke the refreshToken.
 */
$I->haveUserMetaInDatabase( $user, '', true );

/**
 * Lets use the refreshToken to get a new AuthToken.
 *
 * We don't need any special headers here, we just need to do a "refreshJwtAuthToken" mutation
 */
$I->sendPOST( 'http://wp.localhost/graphql', json_encode([
	'query' => '
		mutation RefreshJWTAuthToken( $input: RefreshJwtAuthTokenInput! ){
		  refreshJwtAuthToken( input:$input ) {
		    authToken
		  }
		}
	',
	'variables' => [
		'input' => [
			'clientMutationId' => uniqid(),
			'jwtRefreshToken' => $refreshToken
		]
	]
], true));

/**
 * The repsonse code should be 200, the request is valid and isn't a failed attempt at authentication
 */
$I->seeResponseCodeIs( 200 );

/**
 * Since the request doesn't contain any auth token or refresh token, there should not be an X-JWT-Auth or X-JWT-Refresh
 * token in the headers
 */
$I->dontSeeHttpHeader( 'X-JWT-Refresh' );
$I->dontSeeHttpHeader( 'X-JWT-Auth' );


/**
 * The response should be JSON
 */
$I->seeResponseIsJson();

/**
 * Response code should be 200 as there should be no errors here
 */
$I->seeResponseCodeIs( 200 );

/**
 * Get the JSON response
 */
$response = $I->grabResponse();

/**
 * Convert the response to JSON for making assertions
 */
$response_array = json_decode( $response, true );

/**
 * The request should have no errors
 */
$I->assertArrayNotHasKey( 'errors', $response_array  );

/**
 * A valid request should contain the data in the response
 */
$I->assertArrayHasKey( 'data', $response_array );

/**
 * An unauthenticated request should still be able to access public data though, so let's make sure there's a post returned
 */
$I->assertNotEmpty( $response_array['data']['refreshJwtAuthToken']['authToken'] );