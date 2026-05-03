<?php

declare(strict_types=1);

namespace App\Exceptions\Pos;

/**
 * Thrown when attempting to void a ticket that is not in 'paid' status.
 */
class TicketNotVoidableException extends PosException {}
