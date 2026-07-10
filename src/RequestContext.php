<?php

declare(strict_types=1);

namespace Phresto;

use Phresto\Auth\AuthContext;
use Phresto\Interf\RequestContext as RequestContextInterface;

/**
 * Default request context passed to middleware and controllers.
 *
 * Holds the parsed HTTP request data plus an optional AuthContext. Fields are
 * public for direct, low-overhead access; the interface getters are kept for
 * middleware that prefers them. Auth middleware replaces the wrapped AuthContext
 * via withAuthContext().
 */
class RequestContext implements RequestContextInterface
{
    public string $method;

    public array $route;

    public array $headers;

    public array $body;

    public string $bodyRaw;

    public array $query;

    public ?AuthContext $authContext;

    public function __construct(
        string $method,
        array $route,
        array $headers,
        array $body,
        string $bodyRaw,
        array $query,
        ?AuthContext $authContext = null
    ) {
        $this->method = mb_strtolower($method);
        $this->route = $route;
        $this->headers = $headers;
        $this->body = $body;
        $this->bodyRaw = $bodyRaw;
        $this->query = $query;
        $this->authContext = $authContext;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getRoute(): array
    {
        return $this->route;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getBodyRaw(): string
    {
        return $this->bodyRaw;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getAuthContext(): ?AuthContext
    {
        return $this->authContext;
    }

    public function withAuthContext(AuthContext $authContext): self
    {
        $clone = clone $this;
        $clone->authContext = $authContext;

        return $clone;
    }

    public function withRoute(array $route): self
    {
        $clone = clone $this;
        $clone->route = $route;

        return $clone;
    }
}
