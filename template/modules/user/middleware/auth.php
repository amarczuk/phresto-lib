<?php

declare(strict_types=1);

namespace Phresto\Modules\Middleware;

use Phresto\Auth\AuthContext;
use Phresto\Auth\ClientFingerprint;
use Phresto\Auth\Jwt;
use Phresto\Auth\RevocationCache;
use Phresto\Interf\Middleware;
use Phresto\Interf\RequestContext as RequestContextInterface;
use Phresto\Modules\Model\permission;
use Phresto\Modules\Model\profile;

/**
 * Default user-module authentication middleware.
 *
 * Resolves a JWT into an AuthContext inside the RequestContext. Missing/invalid/
 * expired/revoked tokens fall back to the visitor (guest) profile. Permissions
 * are loaded from the database only when the visitor path is taken; authenticated
 * users carry their permissions inside the JWT.
 */
class auth implements Middleware
{
    public function process(RequestContextInterface $context): RequestContextInterface
    {
        $authContext = $context->getAuthContext() ?: new AuthContext($context->getHeaders());
        $token = Jwt::extractToken($context->getHeaders());
        if ($token) {
            $fingerprint = ClientFingerprint::fromHeaders($context->getHeaders());
            $payload = Jwt::decode($token, $fingerprint);
            if ($payload
                && !empty($payload['jti'])
                && !RevocationCache::isRevoked($payload['jti'])
            ) {
                return $context->withAuthContext($authContext->withUser(
                    (int) $payload['sub'],
                    (string) $payload['email'],
                    (int) $payload['profile'],
                    (string) $payload['profile_name'],
                    (int) $payload['status'],
                    (array) ($payload['permissions'] ?? [])
                ));
            }
        }

        return $context->withAuthContext($this->guestContext($authContext));
    }

    protected function guestContext(AuthContext $context): AuthContext
    {
        $visitor = profile::find([ 'where' => [ 'name' => 'visitor' ], 'limit' => 1 ]);
        $profileId = (!empty($visitor) && !empty($visitor[0])) ? $visitor[0]->getIndex() : 0;
        $profileName = (!empty($visitor) && !empty($visitor[0])) ? $visitor[0]->name : 'visitor';
        $permissions = permission::find([ 'where' => [ 'profile' => $profileId ] ]);

        return $context->withGuest(
            $profileId,
            $profileName,
            $permissions ? self::permissionsToArray($permissions) : []
        );
    }

    protected static function permissionsToArray($permissions): array
    {
        if (!is_array($permissions)) {
            return [];
        }

        return array_map(function ($permission) {
            if (is_array($permission)) {
                return $permission;
            }

            return [
                'profile' => $permission->profile,
                'route' => $permission->route,
                'method' => $permission->method,
                'allow' => $permission->allow,
            ];
        }, $permissions);
    }
}
