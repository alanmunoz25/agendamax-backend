<?php

declare(strict_types=1);

namespace App\Exceptions\Pos;

/**
 * Thrown when attempting to create a ticket for an appointment that already has one.
 */
class TicketAlreadyExistsException extends PosException {}
