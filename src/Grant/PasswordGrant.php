<?php
/**
 * OAuth 2.0 Password grant
 *
 * @package     league/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace League\OAuth2\Server\Grant;

use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\ClientEntity;
use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Event;
use League\OAuth2\Server\Exception;
use League\OAuth2\Server\Util\SecureKey;

/**
 * Password grant class
 */
class PasswordGrant extends AbstractGrant
{
    /**
     * Grant identifier
     *
     * @var string
     */
    protected $identifier = 'password';

    /**
     * Response type
     *
     * @var string
     */
    protected $responseType;

    /**
     * Callback to authenticate a user's name and password
     *
     * @var callable
     */
    protected $callback;

    /**
     * Access token expires in override
     *
     * @var int
     */
    protected $accessTokenTTL;

    public function __construct()
    {
        $this->acceptedParams[] = 'username';
        $this->acceptedParams[] = 'password';
    }

    /**
     * Set the callback to verify a user's username and password
     *
     * @param callable $callback The callback function
     *
     * @return void
     */
    public function setVerifyCredentialsCallback(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Return the callback function
     *
     * @return callable
     *
     * @throws
     */
    protected function getVerifyCredentialsCallback()
    {
        if (is_null($this->callback) || !is_callable($this->callback)) {
            throw new Exception\ServerErrorException('Null or non-callable callback set on Password grant');
        }

        return $this->callback;
    }

    /**
     * Complete the password grant
     *
     * @return array
     *
     * @throws
     */
    public function completeFlow()
    {
        $this->validateParams();

        // Get the required params
        $clientId = $this->getInput('client_id', $this->server->getRequest()->getUser());
        $clientSecret = $this->getInput('client_secret', $this->server->getRequest()->getPassword());

        // Validate client ID and client secret
        $client = $this->getClient($clientId, $clientSecret);

        $username = $this->getInput('username');
        $password = $this->getInput('password');

        // Check if user's username and password are correct
        $userId = call_user_func($this->getVerifyCredentialsCallback(), $username, $password);

        if (is_null($userId)) {
            $this->server->getEventEmitter()->emit(new Event\UserAuthenticationFailedEvent($this->server->getRequest()));
            throw new Exception\InvalidCredentialsException();
        }

        // Validate any scopes that are in the request
        $scopeParam = $this->server->getRequest()->request->get('scope', '');
        $scopes = $this->validateScopes($scopeParam, $client);

        // Create a new session
        $session = new SessionEntity($this->server);
        $session->setOwner('user', $userId);
        $session->associateClient($client);

        // Generate an access token
        $accessToken = new AccessTokenEntity($this->server);
        $accessToken->setId($this->server->generateAccessToken());
        $accessToken->setExpireTime($this->getAccessTokenTTL() + time());
<<<<<<< HEAD
        $accessToken->setClientId($client->getId());
=======
        $accessToken->setSession($session);
>>>>>>> Added session support

        // Associate scopes with the session and access token
        // foreach ($scopes as $scope) {
        //     $accessToken->associateScope($scope);
        // }

<<<<<<< HEAD
=======
        $this->server->getTokenType()->setSession($session);
>>>>>>> Added session support
        $this->server->getTokenType()->setParam('access_token', $accessToken->getId());
        $this->server->getTokenType()->setParam('expires_in', $this->getAccessTokenTTL());

        // Associate a refresh token if set
        if ($this->server->hasGrantType('refresh_token')) {
            $refreshToken = new RefreshTokenEntity($this->server);
            $refreshToken->setId($this->server->generateRefreshToken());
            $refreshToken->setExpireTime($this->server->getGrantType('refresh_token')->getRefreshTokenTTL() + time());
            $refreshToken->setSession($session);

            $this->server->getTokenType()->setParam('refresh_token', $refreshToken->getId());
        }

        // Save everything
<<<<<<< HEAD
        $accessToken->save();
        $this->server->getUsersAccessTokenStorage()->create($accessToken, $user);
=======
        $session->save();
        $accessToken->save();

        if ($this->server->hasGrantType('refresh_token')) {
            $refreshToken->save();
        }
>>>>>>> Added session support

        return $this->server->getTokenType()->generateResponse();
    }
}
