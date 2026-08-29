<?php

namespace App\Services\SocialDistribution;

use RuntimeException;

class SocialProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
