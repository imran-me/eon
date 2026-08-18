<?php

namespace App\Exceptions;

/**
 * Someone tried to settle an expense from a custodian's float for more than that
 * custodian is holding.
 *
 * Its own class, rather than a bare Exception, so the expense controller can turn
 * exactly this into a message for the person filing the receipt and let every
 * other failure keep its normal handling.
 */
class PettyCashOverdrawnException extends \RuntimeException
{
}
