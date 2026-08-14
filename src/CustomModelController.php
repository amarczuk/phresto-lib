<?php

declare(strict_types=1);

namespace Phresto;

use Phresto\Interf\RequestContext as RequestContextInterface;

class CustomModelController extends ModelController
{
    public const CLASSNAME = __CLASS__;
    public const MODELCLASS = 'Phresto\\Module\\Model\\Name';

    public function __construct(?RequestContextInterface $requestContext = null, ?Model $contextModel = null)
    {
        $this->modelName = static::MODELCLASS;
        parent::__construct(static::MODELCLASS, $requestContext, $contextModel);
    }
}
