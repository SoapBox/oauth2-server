<?php
/**
 * OAuth 2.0 Access token entity
 *
 * @package     league/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace League\OAuth2\Server\Entity;

/**
 * Access token entity class
 */
class AccessTokenEntity extends AbstractTokenEntity
{
  protected $refreshToken = null;

    /**
     * Check if access token has an associated scope
     *
     * @param string $scope Scope to check
     *
     * @return bool
     */
    public function hasScope($scope)
    {
        if ($this->scopes === null) {
            $this->getScopes();
        }

        return isset($this->scopes[$scope]);
    }

    /**
     * Return all scopes associated with the access token
     *
     * @return \League\OAuth2\Server\Entity\ScopeEntity[]
     */
    public function getScopes()
    {
        if ($this->scopes === null) {
            $this->scopes = $this->formatScopes(
                $this->server->getAccessTokenStorage()->getScopes($this)
            );
        }

        return $this->scopes;
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $this->server->getAccessTokenStorage()->create(
            $this->getId(),
            $this->getExpireTime(),
            $this->getSession()->getId(),
            $this->getRefreshToken()
        );

        // Associate the scope with the token
        foreach ($this->getScopes() as $scope) {
            $this->server->getAccessTokenStorage()->associateScope($this, $scope);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expire()
    {
        $this->server->getAccessTokenStorage()->delete($this);
    }

    public function setRefreshToken($refreshToken) {
      $this->refreshToken = $refreshToken;
    }

    public function getRefreshToken() {
      return $this->refreshToken;
    }
}
