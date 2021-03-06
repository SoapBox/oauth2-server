<?php
/**
 * OAuth 2.0 Refresh token grant
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
use League\OAuth2\Server\Event;
use League\OAuth2\Server\Exception;
use League\OAuth2\Server\Util\SecureKey;

/**
 * Referesh token grant
 */
class RefreshTokenGrant extends AbstractGrant
{
    /**
     * {@inheritdoc}
     */
    protected $identifier = 'refresh_token';

    /**
     * Refresh token TTL (default = 604800 | 1 week)
     *
     * @var integer
     */
    protected $refreshTokenTTL = 604800;

    /**
     * Rotate token (default = true)
     *
     * @var integer
     */
    protected $refreshTokenRotate = true;

    public function __construct()
    {
        $this->acceptedParams[] = 'refresh_token';
    }

    /**
     * Set the TTL of the refresh token
     *
     * @param int $refreshTokenTTL
     *
     * @return void
     */
    public function setRefreshTokenTTL($refreshTokenTTL)
    {
        $this->refreshTokenTTL = $refreshTokenTTL;
    }

    /**
     * Get the TTL of the refresh token
     *
     * @return int
     */
    public function getRefreshTokenTTL()
    {
        return $this->refreshTokenTTL;
    }

    /**
     * Set the rotation boolean of the refresh token
     * @param bool $refreshTokenRotate
     */
    public function setRefreshTokenRotation($refreshTokenRotate = true)
    {
        $this->refreshTokenRotate = $refreshTokenRotate;
    }

    /**
     * Get rotation boolean of the refresh token
     *
     * @return bool
     */
    public function shouldRotateRefreshTokens()
    {
        return $this->refreshTokenRotate;
    }

    /**
     * {@inheritdoc}
     */
    public function completeFlow()
    {
        // Get the required params
        $clientId = $this->getInput('client_id', $this->server->getRequest()->getUser());
        $clientSecret = $this->getInput('client_secret', $this->server->getRequest()->getPassword());

        // Validate client ID and client secret
        $client = $this->getClient($clientId, $clientSecret);

        $oldRefreshTokenParam = $this->getInput('refresh_token');

        // Validate refresh token
        $oldRefreshToken = $this->server->getRefreshTokenStorage()->get($oldRefreshTokenParam);

        if (($oldRefreshToken instanceof RefreshTokenEntity) === false) {
            if ($this->server->getRefreshTokenStorage()->isConsumed($oldRefreshTokenParam)) {
                $this->server->getEventEmitter()->emit(new Event\RefreshTokenConsumedErrorEvent($this->server->getRequest()));
            }
            throw new Exception\InvalidRefreshException();
        }

        // Ensure the old refresh token hasn't expired
        if ($oldRefreshToken->isExpired() === true) {
            throw new Exception\InvalidRefreshException();
        }

        $session = $oldRefreshToken->getSession();

        // // Get the scopes for the original session
        // $scopes = $this->formatScopes($session->getScopes());
        //
        // // Get and validate any requested scopes
        // $requestedScopesString = $this->server->getRequest()->request->get('scope', '');
        // $requestedScopes = $this->validateScopes($requestedScopesString, $client);
        //
        // // If no new scopes are requested then give the access token the original session scopes
        // if (count($requestedScopes) === 0) {
        //     $newScopes = $scopes;
        // } else {
        //     // The OAuth spec says that a refreshed access token can have the original scopes or fewer so ensure
        //     //  the request doesn't include any new scopes
        //     foreach ($requestedScopes as $requestedScope) {
        //         if (!isset($scopes[$requestedScope->getId()])) {
        //             throw new Exception\InvalidScopeException($requestedScope->getId());
        //         }
        //     }
        //
        //     $newScopes = $requestedScopes;
        // }

        // Generate a new access token and assign it the correct sessions
        $newAccessToken = new AccessTokenEntity($this->server);
        $newAccessToken->setId($this->server->generateAccessToken());
        $newAccessToken->setExpireTime($this->getAccessTokenTTL() + time());
        $newAccessToken->setSession($session);

        // foreach ($newScopes as $newScope) {
        //     $newAccessToken->associateScope($newScope);
        // }

        if ($this->shouldRotateRefreshTokens()) {
            // Expire the old refresh token
            $oldRefreshToken->expire();

            // Generate a new refresh token
            $newRefreshToken = new RefreshTokenEntity($this->server);
            $newRefreshToken->setId($this->server->generateRefreshToken());
            $newRefreshToken->setExpireTime($this->getRefreshTokenTTL() + time());
            $newRefreshToken->setSession($session);
            $newRefreshToken->save();

            $this->server->getTokenType()->setParam('refresh_token', $newRefreshToken->getId());
            $newAccessToken->setRefreshToken($oldRefreshToken->getId());
        } else {
            $this->server->getTokenType()->setParam('refresh_token', $oldRefreshToken->getId());
        }

        // Save the new access token
        $newAccessToken->save();

        $this->server->getTokenType()->setParam('access_token', $newAccessToken->getId());
        $this->server->getTokenType()->setParam('expires_in', $this->getAccessTokenTTL());

        return $this->server->getTokenType()->generateResponse();
    }
}
