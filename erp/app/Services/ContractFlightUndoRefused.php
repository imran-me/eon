<?php

namespace App\Services;

/** A wrong-cancellation undo that would not leave the books right — nothing was changed. */
class ContractFlightUndoRefused extends \RuntimeException {}
