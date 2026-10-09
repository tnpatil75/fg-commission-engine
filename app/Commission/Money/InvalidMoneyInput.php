<?php

namespace App\Commission\Money;

use InvalidArgumentException;

/**
 * An amount or rate string that cannot be accepted. The message is safe to show to users.
 */
class InvalidMoneyInput extends InvalidArgumentException
{
}
