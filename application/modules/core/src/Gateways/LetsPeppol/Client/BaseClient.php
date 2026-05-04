<?php

namespace Core\Gateways\LetsPeppol\Client;

use Core\Gateways\ApiClient;

abstract class BaseClient extends ApiClient
{
    protected function sanitizeForLogging(string $message): string
    {
        return str_replace(["\n", "\r"], '', $message);
    }
}
