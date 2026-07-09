<?php

declare(strict_types=1);

namespace Phresto\Modules\Controller;

use Phresto\Container;
use Phresto\CustomModelController;
use Phresto\Exception\RequestException;
use Phresto\Response;

/**
* Additional user's REST endpoints
*/
class user extends CustomModelController
{
    public const CLASSNAME = __CLASS__;
    public const MODELCLASS = 'Phresto\\Modules\\Model\\user';

    protected $routeMapping = [ 'all' => [ 'id' => 0 ] ];

    protected function auth($methodName, $args = null)
    {

        $hasAccess = $this->currentUser->hasAccess($this->modelName, $methodName);
        if (in_array($methodName, ['authenticate_post', 'register_post', 'current_get' ])) {
            return $hasAccess;
        }

        /**
         * user can only access himself unless it's superuser (status = 2)
         * or it's getting users related to the other model
         */
        return $hasAccess
               && (
                   $this->currentUser->status == 2
                 || (!empty($args[0]) && $this->currentUser->getIndex() == $args[0])
                 || (in_array($methodName, ['head', 'get']) && !empty($this->contextModel))
               );
    }

    /**
    * update record
    * @param id id of record to update
    * @param json model properties
    * @return object updated record
    */
    public function patch($id = null)
    {
        if (!empty($this->contextModel)) {
            throw new RequestException('Bad request', 400);
        }

        if (empty($id)) {
            throw new RequestException('Not found', 404);
        }

        if (empty($this->body)) {
            throw new RequestException('No content', 204);
        }

        $modelInstance = Container::{$this->modelName}($id);
        if (empty($modelInstance->id)) {
            throw new RequestException('Not found', 404);
        }

        // only super user can change status and profile
        if ($this->currentUser->status != 2) {
            if (!empty($this->body['status'])) {
                $this->body['status'] = $modelInstance->status;
            }
            if (!empty($this->body['profile'])) {
                $this->body['profile'] = $modelInstance->profile;
            }
        }

        $modelInstance->update($this->body);
        $modelInstance->save();

        return Response::json($modelInstance);
    }

    /**
    * upsert record
    * @param id (optional)
    * @param json model properties
    * @return object updated record
    */
    public function put($id = null)
    {
        if (!empty($this->contextModel)) {
            throw new RequestException('Bad request', 400);
        }

        if (empty($this->body)) {
            throw new RequestException('No content', 204);
        }

        $modelInstance = Container::{$this->modelName}($id);

        // only super user can change status and profile
        if ($this->currentUser->status != 2) {
            if (!empty($this->body['status'])) {
                $this->body['status'] = $modelInstance->status;
            }
            if (!empty($this->body['profile'])) {
                $this->body['profile'] = $modelInstance->profile;
            }
        }

        $modelInstance->update($this->body);
        $modelInstance->save();

        return Response::json($modelInstance);
    }

    public function authenticate_post(string $email, string $password)
    {
        $user = static::MODELCLASS;
        $token = $user::login($email, $password);

        $ua = (!empty($this->headers['User-Agent'])) ? $this->headers['User-Agent'] : '';
        $encrypted = $token->encrypt($ua);
        setcookie('prsid', $encrypted, 0, '/', null, false, true);

        return Response::json([ 'token' => $encrypted, 'expires' => $token->expires->format(\DateTimeInterface::ATOM) ]);
    }

    public function current_get()
    {
        return Response::json($this->currentUser);
    }

    public function register_post(string $email, string $name, string $password)
    {
        $userClass = static::MODELCLASS;
        $user = new $userClass();
        $user->email = $email;
        $user->name = $name;
        $user->password = $password;
        $user->status = 1;
        $user->profile = 2;

        $user->save();

        return $this->authenticate_post($email, $password);
    }
}
