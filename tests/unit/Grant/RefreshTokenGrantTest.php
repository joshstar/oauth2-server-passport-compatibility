<?php

namespace joshstarTests\Grant;

use joshstar\OAuth2\Server\AuthorizationServer;
use joshstar\OAuth2\Server\Entity\AccessTokenEntity;
use joshstar\OAuth2\Server\Entity\ClientEntity;
use joshstar\OAuth2\Server\Entity\RefreshTokenEntity;
use joshstar\OAuth2\Server\Entity\ScopeEntity;
use joshstar\OAuth2\Server\Entity\SessionEntity;
use joshstar\OAuth2\Server\Grant\RefreshTokenGrant;
use Mockery as M;

class RefreshTokenGrantTest extends \PHPUnit_Framework_TestCase
{
    public function testSetRefreshTokenTTL()
    {
        $grant = new RefreshTokenGrant();
        $grant->setRefreshTokenTTL(86400);

        $property = new \ReflectionProperty($grant, 'refreshTokenTTL');
        $property->setAccessible(true);

        $this->assertEquals(86400, $property->getValue($grant));
    }

    public function testCompleteFlowMissingClientId()
    {
        $this->setExpectedException('joshstar\OAuth2\Server\Exception\InvalidRequestException');

        $_POST['grant_type'] = 'refresh_token';

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlowMissingClientSecret()
    {
        $this->setExpectedException('joshstar\OAuth2\Server\Exception\InvalidRequestException');

        $_POST = [
            'grant_type' => 'refresh_token',
            'client_id'  =>  'testapp',
        ];

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlowInvalidClient()
    {
        $this->setExpectedException('joshstar\OAuth2\Server\Exception\InvalidClientException');

        $_POST = [
            'grant_type' => 'refresh_token',
            'client_id' =>  'testapp',
            'client_secret' =>  'foobar',
        ];

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();

        $clientStorage = M::mock('joshstar\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(null);

        $server->setClientStorage($clientStorage);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlowMissingRefreshToken()
    {
        $this->setExpectedException('joshstar\OAuth2\Server\Exception\InvalidRequestException');

        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
        ];

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();

        $clientStorage = M::mock('joshstar\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $sessionStorage = M::mock('joshstar\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');

        $scopeStorage = M::mock('joshstar\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->requireScopeParam(true);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlowInvalidRefreshToken()
    {
        $this->setExpectedException('joshstar\OAuth2\Server\Exception\InvalidRefreshException');

        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
            'refresh_token' =>  'meh',
        ];

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();

        $clientStorage = M::mock('joshstar\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $refreshTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('get');
        $refreshTokenStorage->shouldReceive('setServer');

        $scopeStorage = M::mock('joshstar\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);
        $server->requireScopeParam(true);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlowExistingScopes()
    {
        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
            'refresh_token' =>  'refresh_token',
        ];

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();

        $clientStorage = M::mock('joshstar\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $sessionStorage = M::mock('joshstar\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([]);
        $sessionStorage->shouldReceive('associateScope');
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            (new SessionEntity($server))
        );

        $accessTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('get')->andReturn(
            (new AccessTokenEntity($server))
        );
        $accessTokenStorage->shouldReceive('delete');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->hydrate(['id' => 'foo']),
        ]);
        $accessTokenStorage->shouldReceive('associateScope');

        $refreshTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('setServer');
        $refreshTokenStorage->shouldReceive('associateScope');
        $refreshTokenStorage->shouldReceive('delete');
        $refreshTokenStorage->shouldReceive('create');
        $refreshTokenStorage->shouldReceive('get')->andReturn(
            (new RefreshTokenEntity($server))->setExpireTime(time() + 86400)
        );

        $scopeStorage = M::mock('joshstar\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->hydrate(['id' => 'foo'])
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);

        $server->addGrantType($grant);
        $response = $server->issueAccessToken();

        $this->assertTrue(array_key_exists('access_token', $response));
        $this->assertTrue(array_key_exists('refresh_token', $response));
        $this->assertTrue(array_key_exists('token_type', $response));
        $this->assertTrue(array_key_exists('expires_in', $response));
    }

    public function testCompleteFlowRequestScopes()
    {
        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
            'refresh_token' =>  'refresh_token',
            'scope'         =>  'foo',
        ];

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();

        $oldSession = (new SessionEntity($server))->associateScope((new ScopeEntity($server))->hydrate(['id' => 'foo']));

        $clientStorage = M::mock('joshstar\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $sessionStorage = M::mock('joshstar\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([]);
        $sessionStorage->shouldReceive('associateScope');
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            $oldSession
        );

        $accessTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('get')->andReturn(
            (new AccessTokenEntity($server))
        );
        $accessTokenStorage->shouldReceive('delete');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->hydrate(['id' => 'foo']),
        ]);
        $accessTokenStorage->shouldReceive('associateScope');

        $refreshTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('setServer');
        $refreshTokenStorage->shouldReceive('associateScope');
        $refreshTokenStorage->shouldReceive('delete');
        $refreshTokenStorage->shouldReceive('create');
        $refreshTokenStorage->shouldReceive('get')->andReturn(
            (new RefreshTokenEntity($server))->setExpireTime(time() + 86400)
        );

        $scopeStorage = M::mock('joshstar\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->hydrate(['id' => 'foo'])
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);

        $server->addGrantType($grant);
        $response = $server->issueAccessToken();

        $this->assertTrue(isset($response['access_token']));
        $this->assertTrue(isset($response['refresh_token']));
        $this->assertTrue(isset($response['token_type']));
        $this->assertTrue(isset($response['expires_in']));
    }

    public function testCompleteFlowExpiredRefreshToken()
    {
        $this->setExpectedException('joshstar\OAuth2\Server\Exception\InvalidRefreshException');

        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
            'refresh_token' =>  'refresh_token',
            'scope'         =>  'foo',
        ];

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();

        $oldSession = (new SessionEntity($server))->associateScope((new ScopeEntity($server))->hydrate(['id' => 'foo']));

        $clientStorage = M::mock('joshstar\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $sessionStorage = M::mock('joshstar\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([]);
        $sessionStorage->shouldReceive('associateScope');
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            $oldSession
        );

        $accessTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('get')->andReturn(
            (new AccessTokenEntity($server))
        );
        $accessTokenStorage->shouldReceive('delete');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->hydrate(['id' => 'foo']),
        ]);
        $accessTokenStorage->shouldReceive('associateScope');

        $refreshTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('setServer');
        $refreshTokenStorage->shouldReceive('associateScope');
        $refreshTokenStorage->shouldReceive('delete');
        $refreshTokenStorage->shouldReceive('create');
        $refreshTokenStorage->shouldReceive('get')->andReturn(
            (new RefreshTokenEntity($server))
        );

        $scopeStorage = M::mock('joshstar\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->hydrate(['id' => 'foo'])
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlowRequestScopesInvalid()
    {
        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
            'refresh_token' =>  'refresh_token',
            'scope'         =>  'blah',
        ];

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();

        $oldSession = (new SessionEntity($server))->associateScope((new ScopeEntity($server))->hydrate(['id' => 'foo']));

        $clientStorage = M::mock('joshstar\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $sessionStorage = M::mock('joshstar\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([]);
        $sessionStorage->shouldReceive('associateScope');
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            $oldSession
        );

        $accessTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('get')->andReturn(
            (new AccessTokenEntity($server))
        );
        $accessTokenStorage->shouldReceive('delete');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->hydrate(['id' => 'foo']),
        ]);
        $accessTokenStorage->shouldReceive('associateScope');

        $refreshTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('setServer');
        $refreshTokenStorage->shouldReceive('associateScope');
        $refreshTokenStorage->shouldReceive('delete');
        $refreshTokenStorage->shouldReceive('create');
        $refreshTokenStorage->shouldReceive('get')->andReturn(
            (new RefreshTokenEntity($server))->setExpireTime(time() + 86400)
        );

        $scopeStorage = M::mock('joshstar\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->hydrate(['id' => 'blah'])
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);

        $server->addGrantType($grant);

        $this->setExpectedException('joshstar\OAuth2\Server\Exception\InvalidScopeException');

        $server->issueAccessToken();
    }

    public function testCompleteFlowRotateRefreshToken()
    {
        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
            'refresh_token' =>  'refresh_token',
        ];

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();

        $clientStorage = M::mock('joshstar\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $sessionStorage = M::mock('joshstar\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([]);
        $sessionStorage->shouldReceive('associateScope');
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            (new SessionEntity($server))
        );

        $accessTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('get')->andReturn(
            (new AccessTokenEntity($server))
        );
        $accessTokenStorage->shouldReceive('delete');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->hydrate(['id' => 'foo']),
        ]);
        $accessTokenStorage->shouldReceive('associateScope');

        $refreshTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('setServer');
        $refreshTokenStorage->shouldReceive('associateScope');
        $refreshTokenStorage->shouldReceive('delete');
        $refreshTokenStorage->shouldReceive('create');
        $refreshTokenStorage->shouldReceive('get')->andReturn(
            (new RefreshTokenEntity($server))->setId('refresh_token')->setExpireTime(time() + 86400)
        );

        $scopeStorage = M::mock('joshstar\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->hydrate(['id' => 'foo'])
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);

        $server->addGrantType($grant);

        $response = $server->issueAccessToken();
        $this->assertTrue(array_key_exists('access_token', $response));
        $this->assertTrue(array_key_exists('refresh_token', $response));
        $this->assertTrue(array_key_exists('token_type', $response));
        $this->assertTrue(array_key_exists('expires_in', $response));
        $this->assertNotEquals($response['refresh_token'], $_POST['refresh_token']);

        $grant->setRefreshTokenRotation(false);
        $response = $server->issueAccessToken();
        $this->assertTrue(array_key_exists('access_token', $response));
        $this->assertTrue(array_key_exists('refresh_token', $response));
        $this->assertTrue(array_key_exists('token_type', $response));
        $this->assertTrue(array_key_exists('expires_in', $response));
        $this->assertEquals($response['refresh_token'], $_POST['refresh_token']);
    }

    public function testCompleteFlowShouldRequireClientSecret()
    {
        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'refresh_token' =>  'refresh_token',
        ];

        $server = new AuthorizationServer();
        $grant = new RefreshTokenGrant();
        $grant->setRequireClientSecret(false);

        $clientStorage = M::mock('joshstar\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $sessionStorage = M::mock('joshstar\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([]);
        $sessionStorage->shouldReceive('associateScope');
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            (new SessionEntity($server))
        );

        $accessTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('get')->andReturn(
            (new AccessTokenEntity($server))
        );
        $accessTokenStorage->shouldReceive('delete');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->hydrate(['id' => 'foo']),
        ]);
        $accessTokenStorage->shouldReceive('associateScope');

        $refreshTokenStorage = M::mock('joshstar\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('setServer');
        $refreshTokenStorage->shouldReceive('associateScope');
        $refreshTokenStorage->shouldReceive('delete');
        $refreshTokenStorage->shouldReceive('create');
        $refreshTokenStorage->shouldReceive('get')->andReturn(
            (new RefreshTokenEntity($server))->setId('refresh_token')->setExpireTime(time() + 86400)
        );

        $scopeStorage = M::mock('joshstar\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->hydrate(['id' => 'foo'])
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);

        $server->addGrantType($grant);

        $response = $server->issueAccessToken();
        $this->assertTrue(array_key_exists('access_token', $response));
        $this->assertTrue(array_key_exists('refresh_token', $response));
        $this->assertTrue(array_key_exists('token_type', $response));
        $this->assertTrue(array_key_exists('expires_in', $response));

    }
}
