<?php

namespace App\Exceptions;

/**
 * An expense was settled from a source that does not hold enough to cover it.
 *
 * The parent of every "that pot is empty" refusal, so the expense desk can catch
 * one type and answer all of them the same way — as a sentence the person filing
 * can act on, never a 500.
 *
 * Subclassed rather than used directly because the two cases read differently to
 * whoever hits them: a custodian holding too little is a cash-issuing problem,
 * an overdrawn bank is usually a missing deposit or the wrong bank picked.
 */
class SettlementSourceOverdrawnException extends \RuntimeException
{
}
