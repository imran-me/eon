<?php

namespace Modules\EON\Intelligence\Support;

/**
 * One thing EON noticed, in words a person can act on.
 *
 * A signal is never a raw number. "completed_mtd = 4" is data; "Output is down
 * 46% on the same days last month, and the fall is their steepest in six
 * months" is a signal. The analyzers emit these; the narrative panel and the
 * printed review sheet render nothing else, so anything EON claims in English
 * is traceable to exactly one analyzer that built it.
 *
 * Severity is what sorts them. It is deliberately a small fixed set: a page
 * that grades its own findings on twenty levels stops being readable.
 */
final class Signal
{
    public const CRITICAL = 'critical';   // Act on this now.
    public const WATCH    = 'watch';      // Worth a question at the next review.
    public const NEUTRAL  = 'neutral';    // Context — often "we cannot tell yet".
    public const GOOD     = 'good';       // Worth saying out loud.

    private const RANK = [
        self::CRITICAL => 0,
        self::WATCH    => 1,
        self::GOOD     => 2,
        self::NEUTRAL  => 3,
    ];

    public function __construct(
        public readonly string $key,
        public readonly string $severity,
        public readonly string $title,
        public readonly ?string $detail = null,
        public readonly ?string $pillar = null,
        /** Anything the view wants to render beside the words — never required. */
        public readonly array $data = [],
    ) {
    }

    public static function critical(string $key, string $title, ?string $detail = null, ?string $pillar = null, array $data = []): self
    {
        return new self($key, self::CRITICAL, $title, $detail, $pillar, $data);
    }

    public static function watch(string $key, string $title, ?string $detail = null, ?string $pillar = null, array $data = []): self
    {
        return new self($key, self::WATCH, $title, $detail, $pillar, $data);
    }

    public static function neutral(string $key, string $title, ?string $detail = null, ?string $pillar = null, array $data = []): self
    {
        return new self($key, self::NEUTRAL, $title, $detail, $pillar, $data);
    }

    public static function good(string $key, string $title, ?string $detail = null, ?string $pillar = null, array $data = []): self
    {
        return new self($key, self::GOOD, $title, $detail, $pillar, $data);
    }

    public function rank(): int
    {
        return self::RANK[$this->severity] ?? 9;
    }

    /** Font Awesome name — the icon set the ERP already loads everywhere. */
    public function icon(): string
    {
        return match ($this->severity) {
            self::CRITICAL => 'fa-circle-exclamation',
            self::WATCH    => 'fa-triangle-exclamation',
            self::GOOD     => 'fa-circle-check',
            default        => 'fa-circle-info',
        };
    }

    public function toArray(): array
    {
        return [
            'key'      => $this->key,
            'severity' => $this->severity,
            'title'    => $this->title,
            'detail'   => $this->detail,
            'pillar'   => $this->pillar,
            'data'     => $this->data,
        ];
    }
}
