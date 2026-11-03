<?php

namespace App\Services;

use App\Models\EmployeePayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmployeePaymentNotifier
{
    /**
     * Send one consolidated notice per payment. Each channel is isolated so a
     * missing address or gateway failure cannot affect the other channels.
     */
    public function send(EmployeePayment $payment): void
    {
        $payment->loadMissing(['employee', 'bank']);
        $employee = $payment->employee;
        if (! $employee) {
            return;
        }

        try {
            NotificationService::notifyEmployeePayment($payment);
        } catch (\Throwable $e) {
            Log::warning('Employee payment push notification failed.', [
                'payment_id' => $payment->id,
                'user_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }

        $date = $payment->payment_date->format('d M Y');
        $time = Carbon::parse((string) $payment->transaction_time)->format('h:i A');
        $method = str($payment->payment_method ?: 'bank')->replace('_', ' ')->title();

        if ($employee->email) {
            try {
                $response = sendBrevoMail(
                    $employee->email,
                    $employee->name,
                    'Salary payment received - '.$payment->reference,
                    view('emails.employee-payment', compact('employee', 'payment', 'date', 'time', 'method'))->render()
                );

                if (is_object($response) && method_exists($response, 'successful') && ! $response->successful()) {
                    Log::warning('Employee payment email gateway failed.', [
                        'payment_id' => $payment->id,
                        'status' => method_exists($response, 'status') ? $response->status() : null,
                    ]);
                } else {
                    Log::info('Employee payment email sent through Brevo.', [
                        'payment_id' => $payment->id,
                        'user_id' => $employee->id,
                        'email' => $employee->email,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Employee payment email failed.', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        if ($employee->phone) {
            try {
                $message = "Dear {$employee->name}, salary payment Tk ".number_format((float) $payment->amount, 2)
                    ." received via {$method}"
                    .($payment->recipient_bank ? " ({$payment->recipient_bank}".($payment->recipient_account ? " A/C {$payment->recipient_account}" : '').')' : '')
                    ." on {$date} {$time}. Ref: {$payment->reference}.";
                $result = sendSms($employee->phone, $message);
                if ($result === false) {
                    throw new \RuntimeException(app()->bound('sms.last_error') ? (string) app('sms.last_error') : 'SMS gateway returned no response.');
                }
                Log::info('Employee payment SMS sent through global SMS helper.', [
                    'payment_id' => $payment->id,
                    'user_id' => $employee->id,
                    'phone_tail' => substr((string) $employee->phone, -4),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Employee payment SMS failed.', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
