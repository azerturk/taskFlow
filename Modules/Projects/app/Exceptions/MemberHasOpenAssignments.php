<?php

namespace Modules\Projects\Exceptions;

use DomainException;

class MemberHasOpenAssignments extends DomainException
{
    public function __construct(public readonly int $count)
    {
        parent::__construct('The member has open assignments.');
    }
}
