<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Make an invalid authenticated request and verify I get a 403 response and cannot access private data, but can still access public data');

$invalidAuthToken = 'invalidAuthToken';

/**
 * Set the Authorization token with an invalid token, and try to request private data.
 *
 * This should return a 403, and should not return any private data for the user, but should still return public data
 */
$I->setHeader( 'Authorization', 'Bearer ' . $invalidAuthToken );
$I->sendPOST( 'http://wp.localhost/graphql', json_encode([
	'query' => '
		{
		  posts(first: 1) {
		   edges { 
		     node {
		       id
		       title
		     }
		   }
		  }
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
 * The repsonse code should be 403, because auth was invalid
 */
$I->seeResponseCodeIs( 403 );

/**
 * Since this is an invalid request, the JWT Auth and JWT Refresh token should not be returned in the response headers
 */
$I->dontSeeHttpHeader( 'X-JWT-Refresh' );
$I->dontSeeHttpHeader( 'X-JWT-Auth' );


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
 * The request should have errors, because it attempted to query data for a user without providing Auth data
 */
$I->assertArrayHasKey( 'errors', $response_array  );

/**
 * A valid request should contain the data in the response
 */
$I->assertArrayHasKey( 'data', $response_array );

/**
 * The viewer should be null in the response, because an unauthenticated request should not be able to access viewer data
 */
$I->assertNull( $response_array['data']['viewer'] );

/**
 * An unauthenticated request should still be able to access public data though, so let's make sure there's a post returned
 */
$I->assertNotEmpty( $response_array['data']['posts']['edges'][0]['node']['id'] );
$I->assertNotEmpty( $response_array['data']['posts']['edges'][0]['node']['title'] );