<?php

namespace Core\Gateways\LetsPeppol\Client;

use Core\Gateways\ApiClient;
use Error;
use ErrorException;
use Throwable;

abstract class BaseClient extends ApiClient
{
    protected function sendGetRequest(string $endpointKey, array $query = [], array $options = []): array
    {
        return $this->sendRequest('GET', $endpointKey, [
            'headers' => $this->buildHeaders(),
            'query' => $query,
        ] + $options);
    }

    protected function sendPostRequest(string $endpointKey, array $payload = [], array $options = []): array
    {
        return $this->sendRequest('POST', $endpointKey, [
            'headers' => $this->buildHeaders(),
            'json' => $payload,
        ] + $options);
    }

    protected function sendRequest(string $method, string $endpointKey, array $options): array
    {
        try {
            $response = $this->request($method, $endpointKey, $options);
            $rawBody = (string) $response->getBody();
            log_message('debug', 'LetsPeppol API response: ' . $this->sanitizeForLogging($rawBody));

            $decoded = json_decode($rawBody, true);

            return is_array($decoded) ? $decoded : [];
        } catch (Error $error) {
            log_message('error', 'LetsPeppol API error: ' . $this->sanitizeForLogging($error->getMessage()));
            throw $error;
        } catch (ErrorException $exception) {
            log_message('error', 'LetsPeppol API exception: ' . $this->sanitizeForLogging($exception->getMessage()));
            throw $exception;
        } catch (Throwable $throwable) {
            log_message('error', 'LetsPeppol API throwable: ' . $this->sanitizeForLogging($throwable->getMessage()));
            throw $throwable;
        }
    }

    protected function sanitizeForLogging(string $message): string
    {
        return str_replace(["\n", "\r"], '', $message);
    }
}
