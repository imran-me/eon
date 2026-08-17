<?php
declare(strict_types=1);

/* Notifications: e-mail via PHP mail() (works on Hostinger) and an optional webhook (WhatsApp/SMS gateway, Slack…). */
final class Notify
{
    public static function send(string $title, string $text, ?string $html = null): array
    {
        $r = ['email' => false, 'webhook' => false];
        $to = (string) Config::get('notify.email_to', '');
        if ($to !== '') {
            $from = (string) Config::get('notify.email_from', 'eon@localhost');
            $headers = "From: EON <{$from}>\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
            $body = $html ?: '<pre style="font-family:system-ui,sans-serif;white-space:pre-wrap">' . htmlspecialchars($text) . '</pre>';
            $r['email'] = @mail($to, '=?UTF-8?B?' . base64_encode($title) . '?=', $body, $headers);
        }
        $hook = (string) Config::get('notify.webhook', '');
        if ($hook !== '' && function_exists('curl_init')) {
            $ch = curl_init($hook);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['title' => $title, 'text' => $text], JSON_UNESCAPED_UNICODE)]);
            curl_exec($ch); $r['webhook'] = curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400; curl_close($ch);
        }
        Log::info('notify', ['title' => $title] + $r);
        return $r;
    }
}
