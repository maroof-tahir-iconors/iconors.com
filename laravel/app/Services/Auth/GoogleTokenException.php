<?php

namespace App\Services\Auth;

use RuntimeException;

/** A Google ID token that failed verification and must not be trusted. */
class GoogleTokenException extends RuntimeException
{
}
