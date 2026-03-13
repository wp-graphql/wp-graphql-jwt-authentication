<?php
$I = new FunctionalTester($scenario);
$I->wantTo('Make an invalid authenticated request and verify that an invalid token causes errors for all fields');

$invalidAuthToken = 'invalidAuthToken';

$I->havePostInDatabase(['post_title' => 'Test Post', 'post_status' => 'publish', 'post_type' => 'post']);
$I->haveHttpHeader('Content-Type', 'application/json');

/**
 * Set the Authorization token with an invalid token, and try to request data.
 *
 * An invalid JWT token will cause the JWT authentication to throw an error,
 * which results in all fields returning null with errors in the response.
 */
$I->setHeader( 'Authorization', 'Bearer ' . $invalidAuthToken );
$I->sendPOST( '/graphql', json_encode([
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
 * WPGraphQL returns 200 with errors in the response body for invalid tokens.
 */
$I->seeResponseCodeIs( 200 );

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
 * The request should have errors because the JWT token is invalid
 */
$I->assertArrayHasKey( 'errors', $response_array  );

/**
 * The data key should still be present in the response
 */
$I->assertArrayHasKey( 'data', $response_array );

/**
 * The viewer should be null because the invalid token cannot authenticate a user
 */
$I->assertNull( $response_array['data']['viewer'] );

/**
 * Posts should also be null because the invalid JWT token causes an error
 * that prevents all field resolution
 */
$I->assertNull( $response_array['data']['posts'] );
