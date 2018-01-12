<?php

$I = new FunctionalTester($scenario);
$I->wantTo('Get Posts with GraphQL');
$I->sendPost( 'https://denverpost.com/graphql', json_encode([
'query' => '{ posts{ edges{ node{ id, title } } } }'
]) );
$I->seeResponseCodeIs( 403 );
$I->seeResponseIsJson();



$res = $I->sendPOST( 'http://wp.localhost/graphql', json_encode([
	'query' => '{ posts{ edges{ node{ id, title } } } }'
]) );
$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();