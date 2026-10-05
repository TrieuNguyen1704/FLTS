<?php

namespace App\Services;

use RuntimeException;

/** A browser-safe failure returned by the internal AI-service boundary. */
class RagServiceException extends RuntimeException
{
}
