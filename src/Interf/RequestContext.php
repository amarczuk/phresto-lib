<?php

declare(strict_types=1);

namespace Phresto\Interf;

use Phresto\Auth\AuthContext;

/**
 * Generic request context passed to middleware.
 *
 * Middleware should be generic: logging, validation, rate limiting, auth, etc.
 * AuthContext lives inside RequestContext so authentication is still available
 * but is not the only thing middleware can inspect.
 */
interface RequestContext
{
    /**
     * HTTP request method in lowercase (get, post, ...).
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * URL path segments after the first class segment.
     *
     * @return array
     */
    public function getRoute(): array;

    /**
     * Request headers.
     *
     * @return array
     */
    public function getHeaders(): array;

    /**
     * Parsed request body.
     *
     * @return array
     */
    public function getBody(): array;

    /**
     * Raw request body.
     *
     * @return string
     */
    public function getBodyRaw(): string;

    /**
     * Query string parameters.
     *
     * @return array
     */
    public function getQuery(): array;

    /**
     * Authentication/authorization context.
     *
     * @return AuthContext|null
     */
    public function getAuthContext(): ?AuthContext;

    /**
     * Return a copy of the context with a different AuthContext.
     *
     * @param AuthContext $authContext
     * @return static
     */
    public function withAuthContext(AuthContext $authContext): self;

    /**
     * Return a copy of the context with a different route.
     *
     * @param array $route
     * @return static
     */
    public function withRoute(array $route): self;
}
