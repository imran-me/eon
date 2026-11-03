<?php

namespace App\Exceptions;

/**
 * A Ticket Orders change (re-issue / reschedule / refund / void) that must
 * not go ahead. Thrown inside the transaction, so nothing of the request is
 * written; TicketChangeController turns it into the dialog's message.
 */
class TicketChangeRefused extends \RuntimeException
{
}
