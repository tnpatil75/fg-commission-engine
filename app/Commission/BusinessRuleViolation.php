<?php

namespace App\Commission;

use DomainException;

/**
 * A request that is well-formed but not allowed by the business rules,
 * e.g. a second active rule for a combination or a repeated manual decision.
 * The message is safe to show to users.
 */
class BusinessRuleViolation extends DomainException
{
}
