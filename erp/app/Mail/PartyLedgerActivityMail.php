<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The party ledger email in its three framings — activity, statement,
 * reminder (see PartyNotifier). Sent synchronously: the automatic notice is
 * already on the queue, and the manual buttons want the outcome back.
 *
 * $data: the PartyNotifier context (party, type, typeLabel, side, code,
 *        company, mode, entries, highlight, closing, labels, date_from/to)
 * $pdf:  the rendered statement bytes, or null when rendering failed.
 */
class PartyLedgerActivityMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data,
        public ?string $pdf = null,
    ) {}

    public function build()
    {
        $code  = $this->data['code'];
        $count = $this->data['entries']->count();

        $subject = match ($this->data['mode'] ?? 'activity') {
            'statement' => "Your ledger statement — {$code} | আপনার লেজার স্টেটমেন্ট",
            'reminder'  => "Payment reminder — outstanding due BDT " . number_format($this->data['closing'], 2) . " — {$code} | পেমেন্ট রিমাইন্ডার",
            default     => ($count === 1 ? 'New entry' : "{$count} new entries") . " on your ledger — {$code} | আপনার লেজারে নতুন এন্ট্রি",
        };

        $mail = $this->subject($subject)->view('emails.party-ledger-activity', $this->data);

        if ($this->pdf) {
            $mail->attachData($this->pdf, 'Statement-' . $code . '-' . now()->format('Ymd') . '.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
