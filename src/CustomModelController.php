<?php

declare(strict_types=1);

namespace Phresto;

use Phresto\Interf\RequestContext as RequestContextInterface;

class CustomModelController extends ModelController
{
    public const CLASSNAME = __CLASS__;
    public const MODELCLASS = 'Phresto\\Module\\Model\\Name';

    public function __construct(?RequestContextInterface $requestContext = null)
    {
        $this->modelName = static::MODELCLASS;
        parent::__construct(static::MODELCLASS, $requestContext);
    }

    protected static function getParameters($method, $className)
    {
        return parent::getParameters($method, static::MODELCLASS);
    }

    public static function discover($all = false, $className = null, $getRelated = true)
    {
        return parent::discover($all, static::MODELCLASS, $getRelated);
    }
}
