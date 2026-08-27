<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by DiscordAccountLinker when a link attempt is refused. The message
 * is written to be shown to the user verbatim, so it must never disclose
 * anything about *other* accounts — "already linked to another account" is
 * deliberately as much as a caller learns.
 */
class AccountLinkRejected extends RuntimeException {}
