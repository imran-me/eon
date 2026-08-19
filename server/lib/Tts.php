<?php
declare(strict_types=1);

/* ============================================================
   Tts — EON's Bangla mouth, because the machine has not got one.

   Checked on the workstation this was built on:
     Windows voices : David, Mark, Zira — all en-US
     Chrome voices  : 19 Google voices — Hindi yes, Bengali no
   So a browser asked to read বাংলা has nothing to read it with,
   on this machine and on most Windows machines. No amount of
   good Bangla text fixes that.

   The answer is to stop depending on the machine: EON renders the
   speech itself and hands the browser an MP3. Providers are tried
   in order and the first that answers wins:

     1. azure   — bn-BD-NabanitaNeural / PradeepNeural. Real
                  Bangladeshi Bangla, free tier 500k chars/month.
                  Set anthropic-style keys in config.local.php.
     2. google  — Google Cloud Text-to-Speech, if a key is set.
     3. translate — the keyless endpoint Google Translate itself
                  uses. Works today with no setup, which is why it
                  is here, but it is undocumented: treat it as the
                  stopgap, not the plan. Disable with
                  'tts' => ['allow_translate' => false].

   Everything is cached by hash under storage/cache/tts, so the
   same sentence is only ever synthesised once.
   ============================================================ */
final class Tts
{
    /** the translate endpoint refuses anything much longer than this */
    private const CHUNK = 180;
    private const MAX_TEXT = 3000;

    public static function enabled(): bool
    {
        return self::provider() !== null;
    }

    /** which provider will actually be used, or null if none can be */
    public static function provider(): ?string
    {
        $c = (array) (Config::get('tts') ?? []);
        if (($c['enabled'] ?? true) === false) return null;
        if (!empty($c['azure']['key']) && !empty($c['azure']['region'])) return 'azure';
        if (!empty($c['google']['key'])) return 'google';
        if (($c['allow_translate'] ?? true) !== false) return 'translate';
        return null;
    }

    public static function status(): array
    {
        $c = (array) (Config::get('tts') ?? []);
        return [
            'provider'  => self::provider(),
            'azure'     => !empty($c['azure']['key']),
            'google'    => !empty($c['google']['key']),
            'translate' => ($c['allow_translate'] ?? true) !== false,
            'cache'     => self::cacheDir(),
            'cached'    => self::cacheDir() !== null ? count(glob(self::cacheDir() . '/*.mp3') ?: []) : 0,
        ];
    }

    private static function cacheDir(): ?string
    {
        $d = EON_ROOT . '/storage/cache/tts';
        if (!is_dir($d) && !@mkdir($d, 0775, true) && !is_dir($d)) return null;
        return $d;
    }

    /** the voice name for a language, honouring config */
    private static function voiceFor(string $lang): string
    {
        $c = (array) (Config::get('tts') ?? []);
        if ($lang === 'bn') return (string) ($c['azure']['voice_bn'] ?? 'bn-BD-NabanitaNeural');
        return (string) ($c['azure']['voice_en'] ?? 'en-US-AriaNeural');
    }

    /**
     * Speak this text. Returns ['ok', 'mime', 'bytes', 'path', 'provider', 'cached']
     * or ['ok' => false, 'error' => …].
     */
    public static function speak(string $text, string $lang = 'bn'): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') return ['ok' => false, 'error' => 'nothing to say'];
        if (mb_strlen($text) > self::MAX_TEXT) $text = mb_substr($text, 0, self::MAX_TEXT);
        $lang = $lang === 'bn' ? 'bn' : 'en';

        $provider = self::provider();
        if ($provider === null) return ['ok' => false, 'error' => 'no speech provider is configured'];

        $dir = self::cacheDir();
        $key = sha1($provider . '|' . $lang . '|' . self::voiceFor($lang) . '|' . $text);
        $file = $dir !== null ? $dir . '/' . $key . '.mp3' : null;

        if ($file !== null && is_file($file) && filesize($file) > 512) {
            return ['ok' => true, 'mime' => 'audio/mpeg', 'bytes' => filesize($file),
                    'path' => $file, 'provider' => $provider, 'cached' => true];
        }

        $audio = null;
        $tried = [];
        foreach (self::chain($provider) as $p) {
            $tried[] = $p;
            $audio = self::render($p, $text, $lang);
            if ($audio !== null && strlen($audio) > 512) { $provider = $p; break; }
            $audio = null;
        }
        if ($audio === null) {
            Log::warn('tts failed', ['tried' => $tried, 'lang' => $lang]);
            return ['ok' => false, 'error' => 'every speech provider failed', 'tried' => $tried];
        }

        // the cache is what makes a demo safe: once a sentence has been rendered
        // it never needs the network again, whatever the provider does later
        if ($file !== null) @file_put_contents($file, $audio, LOCK_EX);
        return ['ok' => true, 'mime' => 'audio/mpeg', 'bytes' => strlen($audio),
                'path' => $file, 'data' => $audio, 'provider' => $provider, 'cached' => false];
    }

    /** preferred provider first, then whatever else might still work */
    private static function chain(string $first): array
    {
        $c = (array) (Config::get('tts') ?? []);
        $all = [];
        if (!empty($c['azure']['key'])) $all[] = 'azure';
        if (!empty($c['google']['key'])) $all[] = 'google';
        if (($c['allow_translate'] ?? true) !== false) $all[] = 'translate';
        return array_values(array_unique(array_merge([$first], $all)));
    }

    private static function render(string $provider, string $text, string $lang): ?string
    {
        switch ($provider) {
            case 'azure':     return self::azure($text, $lang);
            case 'google':    return self::google($text, $lang);
            case 'translate': return self::translate($text, $lang);
        }
        return null;
    }

    /* ---------------- providers ---------------- */

    /** Azure Speech — the one that actually sounds Bangladeshi */
    private static function azure(string $text, string $lang): ?string
    {
        $c = (array) (Config::get('tts.azure') ?? []);
        $key = (string) ($c['key'] ?? '');
        $region = (string) ($c['region'] ?? '');
        if ($key === '' || $region === '') return null;

        $voice = self::voiceFor($lang);
        $locale = $lang === 'bn' ? 'bn-BD' : 'en-US';
        $ssml = '<speak version="1.0" xml:lang="' . $locale . '">'
              . '<voice name="' . htmlspecialchars($voice, ENT_QUOTES | ENT_XML1) . '">'
              . '<prosody rate="-4%">' . htmlspecialchars($text, ENT_QUOTES | ENT_XML1) . '</prosody>'
              . '</voice></speak>';

        return self::post(
            'https://' . $region . '.tts.speech.microsoft.com/cognitiveservices/v1',
            $ssml,
            [
                'Ocp-Apim-Subscription-Key: ' . $key,
                'Content-Type: application/ssml+xml',
                'X-Microsoft-OutputFormat: audio-24khz-48kbitrate-mono-mp3',
                'User-Agent: EON',
            ]
        );
    }

    /** Google Cloud Text-to-Speech */
    private static function google(string $text, string $lang): ?string
    {
        $key = (string) (Config::get('tts.google.key') ?? '');
        if ($key === '') return null;
        $body = json_encode([
            'input' => ['text' => $text],
            'voice' => ['languageCode' => $lang === 'bn' ? 'bn-IN' : 'en-US'],
            'audioConfig' => ['audioEncoding' => 'MP3', 'speakingRate' => 0.96],
        ], JSON_UNESCAPED_UNICODE);
        $raw = self::post('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . rawurlencode($key),
            (string) $body, ['Content-Type: application/json']);
        if ($raw === null) return null;
        $j = json_decode($raw, true);
        $b64 = $j['audioContent'] ?? null;
        return is_string($b64) ? (base64_decode($b64) ?: null) : null;
    }

    /**
     * The endpoint Google Translate's own speaker button uses. No key, works
     * today — and undocumented, so it is the stopgap. Long text has to be cut
     * into pieces; MP3 frames concatenate cleanly for playback.
     */
    private static function translate(string $text, string $lang): ?string
    {
        $out = '';
        foreach (self::chunks($text) as $part) {
            $mp3 = null;
            // this endpoint rate-limits. Two quick retries turn a stumble into a
            // pause instead of a failed answer in front of a room.
            foreach ([0, 400000, 1200000] as $wait) {
                if ($wait) usleep($wait);
                foreach (['tw-ob', 'gtx'] as $client) {
                    $url = 'https://translate.google.com/translate_tts'
                         . '?ie=UTF-8&client=' . $client . '&ttsspeed=0.95'
                         . '&tl=' . ($lang === 'bn' ? 'bn' : 'en')
                         . '&total=1&idx=0&textlen=' . mb_strlen($part)
                         . '&q=' . rawurlencode($part);
                    $mp3 = self::get($url, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                                            'Referer: https://translate.google.com/']);
                    if ($mp3 !== null && strlen($mp3) >= 256) break 2;
                    $mp3 = null;
                }
            }
            if ($mp3 === null) return null;
            $out .= $mp3;
        }
        return $out !== '' ? $out : null;
    }

    /** split on sentence ends, then on commas, never mid-word */
    public static function chunks(string $text): array
    {
        if (mb_strlen($text) <= self::CHUNK) return [$text];
        $sentences = preg_split('/(?<=[।.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $out = [];
        $buf = '';
        foreach ($sentences as $s) {
            while (mb_strlen($s) > self::CHUNK) {
                $cut = self::breakPoint($s);
                $out[] = trim(mb_substr($s, 0, $cut));
                $s = trim(mb_substr($s, $cut));
            }
            if ($buf !== '' && mb_strlen($buf) + mb_strlen($s) + 1 > self::CHUNK) { $out[] = trim($buf); $buf = ''; }
            $buf = $buf === '' ? $s : $buf . ' ' . $s;
        }
        if (trim($buf) !== '') $out[] = trim($buf);
        return array_values(array_filter($out, fn($x) => $x !== ''));
    }

    private static function breakPoint(string $s): int
    {
        $window = mb_substr($s, 0, self::CHUNK);
        foreach ([',', '—', ';', ':', ' '] as $sep) {
            $p = mb_strrpos($window, $sep);
            if ($p !== false && $p > self::CHUNK * 0.5) return $p + 1;
        }
        return self::CHUNK;
    }

    /* ---------------- transport ---------------- */

    private static function get(string $url, array $headers = []): ?string
    {
        return self::send($url, null, $headers);
    }

    private static function post(string $url, string $body, array $headers = []): ?string
    {
        return self::send($url, $body, $headers);
    }

    private static function send(string $url, ?string $body, array $headers): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_USERAGENT => 'EON',
            ]);
            if ($body !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
            $out = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($out === false || $code < 200 || $code >= 300) {
                Log::warn('tts http', ['url' => parse_url($url, PHP_URL_HOST), 'code' => $code, 'err' => $err]);
                return null;
            }
            return (string) $out;
        }
        if (!ini_get('allow_url_fopen')) return null;
        $ctx = stream_context_create(['http' => [
            'method' => $body === null ? 'GET' : 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'timeout' => 20,
            'ignore_errors' => true,
        ]]);
        $out = @file_get_contents($url, false, $ctx);
        return $out === false ? null : (string) $out;
    }
}
