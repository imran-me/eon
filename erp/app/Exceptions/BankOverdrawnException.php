<?php

namespace App\Exceptions;

/**
 * An expense would take a bank account below zero.
 *
 * A bank is an asset: a credit balance on one means the ledger claims money left
 * an account that never held it. That is not a small reporting blemish — the
 * account feeds Total Bank Balance on every dashboard and the balance sheet, so
 * one overdrawn bank makes both wrong and nothing anywhere says so.
 *
 * Petty cash floats have been guarded against exactly this since the float
 * feature was built; banks were not, which is the wrong way round given the
 * amounts involved. This closes that gap.
 *
 * NOTE: if a bank ever genuinely has an overdraft facility, this must become a
 * per-bank allowance rather than a blanket rule — the chart already carries
 * 2420 Bank Overdraft for that case. No account uses it today.
 */
class BankOverdrawnException extends SettlementSourceOverdrawnException
{
}
