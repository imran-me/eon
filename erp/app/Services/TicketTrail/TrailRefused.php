<?php

namespace App\Services\TicketTrail;

/** A Trail correction that cannot be posted — the message is shown to the user as is. */
class TrailRefused extends \RuntimeException {}
