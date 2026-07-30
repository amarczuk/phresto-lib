<?php

declare(strict_types=1);

namespace Phresto;

use Phresto\Exception\RequestException;
use Phresto\Interf\RequestContext as RequestContextInterface;
use TypeError;

class ModelController extends Controller
{
    public const CLASSNAME = __CLASS__;

    protected $routeMapping = [ 'all' => [ 'id' => 0 ] ];

    protected string $modelName;

    protected null|Model $contextModel;

    protected string $methodName;

    protected static $type = 'model';

    public function __construct(string $modelName, ?RequestContextInterface $requestContext = null, ?Model $contextModel = null)
    {
        $this->modelName = $modelName;
        $this->contextModel = $contextModel;
        parent::__construct($requestContext);
    }

    public function exec()
    {
        list($method, $args) = $this->getMethod();
        $this->methodName = $method->name;
        if (!$this->auth($method->name, $args)) {
            throw new RequestException('Unauthorized', 401);
        }

        if ($this->hasNextRoute()) {
            $route = $this->getNextRoute();

            return $this->escalate((!empty($this->requestContext->route[0])) ? $this->requestContext->route[0] : 0, $route[0]);
        }

        $method->setAccessible(true);

        try {
            return $method->invokeArgs($this, $args);
        } catch (TypeError $error) {
            error_log($error->getMessage());

            throw new RequestException('Bad request', 400);
        }
    }

    protected function hasNextRoute()
    {
        $routeMapping = $this->getRouteMapping($this->methodName);

        return count($routeMapping) < count($this->requestContext->route);
    }

    protected function auth($methodName, $args = null)
    {
        return $this->authContext->hasAccess($this->modelName, $methodName);
    }

    protected function getNextRoute()
    {
        $routeMapping = $this->getRouteMapping($this->methodName);
        $cnt = count($routeMapping);
        $route = $this->requestContext->route;
        for ($i = 0; $i < $cnt; $i++) {
            array_shift($route);
        }

        return $route;
    }

    protected function escalate($id, $model)
    {
        $thisModel = Container::{$this->modelName}();
        if (!empty($this->contextModel)) {
            $thisModel->setRelatedById($this->contextModel, $id);
        } else {
            $thisModel->setById($id);
        }

        $thisModelName = $this->modelName;
        if (!$thisModel->getIndex() || !$thisModelName::isRelated($model)) {
            throw new RequestException('Not found', 404);
        }

        $modelClass = 'Phresto\\Modules\\Model\\' . $model;
        $newRoute = $this->getNextRoute();
        array_shift($newRoute);
        $childContext = $this->requestContext->withRoute($newRoute);
        $modelContr = Container::{'Phresto\\ModelController'}($modelClass, $childContext, $thisModel);

        return $modelContr->exec();
    }

    /**
    * check if record exists, returns count of the collection in X-Count header
    * @param id record's index
    * @return 200 - found or 404 - not found
    */
    public function head($id = null)
    {
        $modelInstance = Container::{$this->modelName}();

        if (empty($id) && empty($this->contextModel)) {
            header('X-Count: ' . $modelInstance::count($this->query));

            return null;
        }

        if (empty($this->contextModel)) {
            $modelInstance->setById($id);
        } else {
            if (empty($id)) {
                header('X-Count: ' . $modelInstance::countRelated($this->contextModel));

                return null;
            }

            $modelInstance->setRelatedById($this->contextModel, $id);
        }

        if (empty($modelInstance->getIndex())) {
            throw new RequestException('Not found', 404);
        }

        header('X-Count: 1');

        return null;
    }

    /**
    * get record
    * @param id record's index (all if empty)
    * @return object / array of objects
    */
    public function get($id = null)
    {
        if (empty($id) && empty($this->contextModel)) {
            $modelName = $this->modelName;

            return Response::json($modelName::find($this->query));
        }

        $modelInstance = Container::{$this->modelName}();
        if (empty($this->contextModel)) {
            $modelInstance->setById($id);
        } else {
            if (empty($id)) {
                $modelName = $this->modelName;

                return Response::json($modelName::findRelated($this->contextModel, $this->query));
            }
            $modelInstance->setRelatedById($this->contextModel, $id);
        }

        if (empty($modelInstance->getIndex())) {
            throw new RequestException('Not found', 404);
        }

        return Response::json($modelInstance);
    }

    /**
    * create record
    * @param json model properties
    * @return object created record
    */
    public function post()
    {
        $modelInstance = Container::{$this->modelName}($this->body);

        if (!empty($this->contextModel)) {
            $relation = $modelInstance->getRelation($this->contextModel->getName());
            if (in_array($relation['type'], ['1:n', '1>1'])) {
                throw new RequestException('Bad request', 400);
            }

            $fk = $relation['index'];
            $related = $relation['field'];
            $modelInstance->$fk = $this->contextModel->$related;
        }

        $modelInstance->save();

        return Response::json($modelInstance);
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
        $modelInstance->update($this->body);
        $modelInstance->save();

        return Response::json($modelInstance);
    }

    /**
    * delete record
    * @param id
    * @return object deleted record
    */
    public function delete($id = null)
    {
        if (empty($id)) {
            throw new RequestException('Not found', 404);
        }

        $modelInstance = Container::{$this->modelName}();

        if (!empty($this->contextModel)) {
            $modelInstance->setRelatedById($this->contextModel, $id);
        } else {
            $modelInstance->setById($id);
        }

        if (empty($modelInstance->getIndex())) {
            throw new RequestException('Not found', 404);
        }

        $modelInstance->delete();

        return Response::json($modelInstance);
    }
}
