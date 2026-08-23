<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a Discord user clicks a component/submits a Modal belonging to
 * someone else's game (see DiscordCombleGame's "u" state key). Caught by
 * DiscordInteractionController and turned into a private (ephemeral) bounce
 * message that leaves the shared, publicly-visible game message untouched.
 */
class DiscordInteractionUnauthorized extends RuntimeException
{
    public function __construct(string $message = "This isn't your Comble game — run `/csk comble` to start your own!")
    {
        parent::__construct($message);
    }
}
