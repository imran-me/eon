<?php

namespace Modules\EON\Intelligence\Scoring;

/**
 * One column of the EON score: a 0-100 reading of one aspect of somebody's
 * work, the weight it carries, and — the part that matters — the sentence that
 * says what the number was read from.
 *
 * Every pillar must be able to answer "why". A score with no stated basis is a
 * rumour with a decimal point, and this one gets attached to a person's review.
 * `basis` is rendered directly under the bar on the dossier.
 *
 * A pillar with no evidence scores null, not zero. Somebody with no rated tasks
 * has not scored 0 on quality; the correct reading is "not known", and the
 * total re-weights itself around the pillars that do have evidence.
 */
final class Pillar
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?float $score,
        public readonly float $weight,
        public readonly string $basis,
        /** The raw figures behind the score, for the tooltip and the print sheet. */
        public readonly array $facts = [],
    ) {
    }

    public function known(): bool
    {
        return $this->score !== null;
    }

    public function rounded(): ?int
    {
        return $this->score === null ? null : (int) round($this->score);
    }

    /** The colour band, shared by the bar, the chip and the print sheet. */
    public function tone(): string
    {
        if ($this->score === null) {
            return 'unknown';
        }

        return match (true) {
            $this->score >= 80 => 'strong',
            $this->score >= 60 => 'fair',
            $this->score >= 40 => 'weak',
            default            => 'poor',
        };
    }

    public function toArray(): array
    {
        return [
            'key'    => $this->key,
            'label'  => $this->label,
            'score'  => $this->rounded(),
            'weight' => $this->weight,
            'basis'  => $this->basis,
            'tone'   => $this->tone(),
            'facts'  => $this->facts,
        ];
    }
}
