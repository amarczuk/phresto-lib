<?php

declare(strict_types=1);

namespace Phresto\Interf;

/**
 * A middleware processes a RequestContext before a request reaches a controller.
 *
 * Middleware can be registered globally on Router::addMiddleware() or declared
 * on a controller/model via a static $middlewares property. It receives the full
 * request context (method, route, headers, body, query, auth) and must return a
 * (possibly modified) RequestContext.
 */
interface Middleware
{
    /**
     * Process the incoming request context.
     *
     * @param RequestContext $context
     * @return RequestContext
     */
    public function process(RequestContext $context): RequestContext;
}
