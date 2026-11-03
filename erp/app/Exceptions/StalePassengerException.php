<?php

namespace App\Exceptions;

/**
 * A contract flight passenger changed between the screen loading and this
 * request reaching the database — someone else (or a double click) acted on
 * him first. Thrown inside the transaction, so nothing of this request is
 * written; the controller turns it into a "reload" message.
 */
class StalePassengerException extends \RuntimeException
{
}
