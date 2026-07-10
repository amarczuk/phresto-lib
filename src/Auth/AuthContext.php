<?php

declare(strict_types=1);

namespace Phresto\Auth;

/**
 * Immutable-ish request context carrying authentication/authorization state.
 *
 * Router middleware builds this object and passes it to controllers. Controllers
 * use it for access checks instead of loading the full user model on every
 * request.
 */
class AuthContext
{
    /** @var int|null */
    protected $userId;

    /** @var string|null */
    protected $email;

    /** @var int|null */
    protected $profileId;

    /** @var string|null */
    protected $profileName;

    /** @var int|null */
    protected $status;

    /** @var bool */
    protected $authenticated = false;

    /** @var array */
    protected $permissions = [];

    /** @var array */
    protected $headers = [];

    /** @var string|null */
    protected $token;

    public function __construct(array $headers = [], ?string $token = null)
    {
        $this->headers = $headers;
        $this->token = $token;
    }

    public function withUser(int $userId, string $email, int $profileId, string $profileName, int $status, array $permissions): self
    {
        $clone = clone $this;
        $clone->userId = $userId;
        $clone->email = $email;
        $clone->profileId = $profileId;
        $clone->profileName = $profileName;
        $clone->status = $status;
        $clone->permissions = $permissions;
        $clone->authenticated = true;

        return $clone;
    }

    public function withGuest(int $profileId, string $profileName, array $permissions): self
    {
        $clone = clone $this;
        $clone->userId = null;
        $clone->email = null;
        $clone->profileId = $profileId;
        $clone->profileName = $profileName;
        $clone->status = null;
        $clone->permissions = $permissions;
        $clone->authenticated = false;

        return $clone;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getProfileId(): ?int
    {
        return $this->profileId;
    }

    public function getProfileName(): ?string
    {
        return $this->profileName;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Check whether the context has access to a class/method pair.
     *
     * @param string $class   Fully-qualified or short controller/model class name
     * @param string $method  Method name (may contain "_get"/_post suffix)
     * @return bool
     */
    public function hasAccess(string $class, string $method): bool
    {
        $class = substr($class, mb_strrpos($class, '\\') + 1);
        $route = $class;
        if (mb_strpos($method, '_') !== false) {
            $tmp = explode('_', $method);
            $route = $class . '/' . $tmp[0];
            $method = $tmp[1];
        }

        foreach ($this->permissions as $permission) {
            if ((int) $permission['profile'] !== (int) $this->profileId) {
                continue;
            }
            $routeMatch = $permission['route'] === $route
                || $permission['route'] === '*'
                || $permission['route'] === "{$class}/*";
            $methodMatch = $permission['method'] === $method
                || $permission['method'] === '*';
            if ($routeMatch && $methodMatch) {
                return (bool) $permission['allow'];
            }
        }

        return false;
    }
}
