<?php

namespace Modules\EON\Intelligence\Analyzers;

use Modules\EON\Intelligence\Assessment;
use Modules\EON\Intelligence\Baseline;

/**
 * One question EON asks about a person.
 *
 * This is the extension point. Adding a new dimension to the evaluation —
 * attendance against task output, project-level load, a per-department rule —
 * means writing one class here and adding one line to EonEngine::analyzers().
 * Nothing else in EON, and nothing at all in the ERP, has to change.
 *
 * The contract every analyzer keeps:
 *   • It reads what is already on the Assessment and never queries anything.
 *     All data arrives through TaskMetrics, so the numbers stay consistent and
 *     the engine stays testable without a database.
 *   • It may add ONE pillar at most (a scored dimension) and any number of
 *     signals (things worth saying).
 *   • It must be silent when it has no evidence. A pillar with no basis scores
 *     null, and no analyzer may invent a finding from an empty series — an EON
 *     page full of confident statements about somebody with three tasks is
 *     worse than an empty one.
 *   • It must not throw. The engine catches anyway, but an analyzer that dies
 *     costs its own dimension and the page still has to render.
 */
interface Analyzer
{
    public function key(): string;

    public function analyze(Assessment $assessment, Baseline $baseline): void;
}
