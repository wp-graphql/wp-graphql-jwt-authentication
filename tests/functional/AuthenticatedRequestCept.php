<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Make an authenticated request to generate a Refresh token');

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
$I->seeResponseIsJson();

$response = $I->grabResponse();
$response_array = json_decode( $response, true );
$I->assertArrayNotHasKey( 'errors', $response_array  );
$I->assertArrayHasKey( 'data', $response_array );

$authToken = $response_array['data']['login']['authToken'];
$refreshToken = $response_array['data']['login']['refreshToken'];

/**
 * Set the Authorization header using the authToken retrieved in the previous request.
 * The authToken can be used to access resources (or mutate data) on behalf of the user it was issued for.
 * Here we will make a request to get data about the user, using the authToken as the mechanism for setting
 * the current user.
 */
$I->setHeader( 'Authorization', 'Bearer ' . $authToken );
$I->sendPOST( 'http://wp.localhost/graphql', json_encode([
	'query' => '
		{
		  viewer {
		    username
		    jwtAuthToken
		    jwtUserSecret
		    jwtRefreshToken
		    jwtAuthExpiration
		    isJwtAuthSecretRevoked
		  }
		}
	',
], true));

/**
 * The repsonse code should be 200
 */
$I->seeResponseCodeIs( 200 );

/**
 * Grab the Refresh header. Because the request was properly authenticated, there should
 * be a valid refresh header in the response. This refresh token should be stored
 * for later use. The authTokens are short-lived, so the client can check the expiration of
 * the authToken before making authenticated requests, and if the token is expired, it can use the
 * refresh token to ask for a new authToken. Additionally, if any authenticated request returns a 403 status,
 * that means the token was invalid (expired or otherwise), and that should trigger the client to refresh the authToken
 * using the refresh token.
 *
 * If the request to refresh an authToken using a refresh token fails, that means that the refresh token has been revoked,
 * or perhaps the user no longer exists, or something else is preventing users from obtaining new authTokens.
 *
 * At that point, the user can attempt to authenticate via a login mutation again, and if that fails, the user probably
 * is not supposed to have access to the system, or a system administrator would need to be contacted because some other
 * forces are at work preventing user auth. . . I'd probably blame the Inform video plugin at this point :wink:
 */
$refreshTokenHeader = $I->grabHttpHeader('X-JWT-Refresh' );
$I->assertNotEmpty( $refreshTokenHeader );

/**
 * The response should be JSON
 */
$I->seeResponseIsJson();

/**
 * Get the JSON response
 */
$response = $I->grabResponse();

/**
 * Convert the response to JSON for making assertions
 */
$response_array = json_decode( $response, true );

/**
 * The request should be valid, so we expect no errors
 */
$I->assertArrayNotHasKey( 'errors', $response_array  );

/**
 * A valid request should contain the data in the response
 */
$I->assertArrayHasKey( 'data', $response_array );

/**
 * The username of the viewer should match the username for the Token we retrieved and sent a request with
 */
$I->assertEquals( $username, $response_array['data']['viewer']['username'] );

/**
 * The request should provide a new jwtAuthToken that we can use for future requests
 */
$I->assertNotEmpty( $response_array['data']['viewer']['jwtAuthToken'] );

/**
 * The request should provide the secret for the user, because the user has access to see their own JWT secret
 */
$I->assertNotEmpty( $response_array['data']['viewer']['jwtUserSecret'] );

/**
 * The request should provide a new JWT Refresh Token that can be used for future requests to get a new AccessToken
 */
$I->assertNotEmpty( $response_array['data']['viewer']['jwtRefreshToken'] );

/**
 * The request should provide info on the auth expiration for the user. This field is useful for building an interface
 * where the user can see how long their expiration is and can then mutate the expiration timeframe, should there be
 * per-user customization of expiration settings.
 */
$I->assertNotEmpty( $response_array['data']['viewer']['jwtAuthExpiration'] );

/**
 * The JWT should not be revoked for this user, so this assertion should be false
 */
$I->assertFalse( $response_array['data']['viewer']['isJwtAuthSecretRevoked'] );