<?php

namespace App\Mail;

use App\Models\VisaSale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VoucherMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VisaSale $visaSale,
        public string $pdfContent,
    ) {}

    public function build()
    {
        return $this->subject('Visa Sale Voucher - ' . $this->visaSale->invoice_number)
            ->markdown('emails.voucher')
            ->attachData($this->pdfContent, $this->visaSale->invoice_number . '.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
