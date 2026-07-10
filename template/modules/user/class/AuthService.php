<?php

declare(strict_types=1);

namespace Phresto\Modules;

use DateTime;
use DateTimeInterface;
use Exception;
use Phresto\Auth\AuthContext;
use Phresto\Auth\ClientFingerprint;
use Phresto\Auth\Jwt;
use Phresto\Auth\RevocationCache;
use Phresto\Modules\Model\permission;
use Phresto\Modules\Model\profile;
use Phresto\Modules\Model\user;

/**
 * Authentication helpers for the user module.
 *
 * This service is intentionally separate from the user model and controller:
 * password verification, JWT issuance, revocation and profile/permission loading
 * live here so that controllers stay thin and the framework core stays
 * agnostic of the user schema.
 */
class AuthService
{
    /**
     * Authenticate a user and return a signed JWT bound to the client fingerprint.
     *
     * @param string $email
     * @param string $password
     * @param array  $headers
     * @return array ['token' => string, 'expires' => string]
     * @throws Exception on invalid credentials
     */
    public static function login(string $email, string $password, array $headers): array
    {
        if (empty($email) || empty($password)) {
            throw new Exception('Blank password or email');
        }

        $users = user::find([ 'where' => [ 'email' => $email ], 'limit' => 1 ]);
        if (empty($users) || empty($users[0]) || empty($users[0]->getIndex())) {
            throw new Exception('No user found');
        }

        $user = $users[0];
        if (!password_verify($password, $user->password)) {
            throw new Exception('No user found');
        }

        if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
            $user->password = $password;
        }

        $user->last_login = new DateTime();
        $user->save();

        return self::issueToken($user, $headers);
    }

    /**
     * Issue a new JWT for a user.
     *
     * @param user  $user
     * @param array $headers
     * @return array ['token' => string, 'expires' => string]
     */
    public static function issueToken(user $user, array $headers): array
    {
        $profile = profile::find([
            'where' => [ 'id' => $user->profile ],
            'limit' => 1,
        ]);
        $profileName = (!empty($profile) && !empty($profile[0])) ? (string) $profile[0]->name : 'user';
        $permissions = permission::find([ 'where' => [ 'profile' => $user->profile ] ]);

        $ttlDays = 7;
        $now = time();
        $exp = $now + ($ttlDays * 86400);
        $jti = self::generateJti();

        $payload = [
            'sub' => $user->getIndex(),
            'email' => $user->email,
            'profile' => $user->profile,
            'profile_name' => $profileName,
            'status' => $user->status,
            'permissions' => self::permissionsToArray($permissions),
            'fp' => ClientFingerprint::fromHeaders($headers),
            'iat' => $now,
            'exp' => $exp,
            'jti' => $jti,
        ];

        return [
            'token' => Jwt::encode($payload),
            'expires' => (new DateTime())->setTimestamp($exp)->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * Revoke a JWT so it can no longer be used.
     *
     * @param string $token
     * @param array  $headers
     */
    public static function logout(string $token, array $headers): void
    {
        $fingerprint = ClientFingerprint::fromHeaders($headers);
        $payload = Jwt::decode($token, $fingerprint);
        if (!empty($payload['jti']) && !empty($payload['exp'])) {
            RevocationCache::revoke($payload['jti'], (int) $payload['exp']);
        }
    }

    /**
     * Load the user model that corresponds to an authenticated context.
     *
     * @param AuthContext $context
     * @return user
     */
    public static function userFromContext(AuthContext $context): user
    {
        if ($context->isAuthenticated() && $context->getUserId()) {
            $users = user::find([
                'where' => [ 'id' => $context->getUserId() ],
                'limit' => 1,
            ]);
            if (!empty($users) && !empty($users[0])) {
                return $users[0];
            }
        }

        $guest = new user();
        $profile = profile::find([ 'where' => [ 'name' => 'visitor' ], 'limit' => 1 ]);
        $guest->profile = (!empty($profile) && !empty($profile[0]))
            ? $profile[0]->getIndex()
            : null;

        return $guest;
    }

    protected static function permissionsToArray($permissions): array
    {
        if (!is_array($permissions)) {
            $permissions = [];
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

    protected static function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }
}
