<?php

namespace Core\Gateways\LetsPeppol\Transformers;

use Core\Gateways\LetsPeppol\DTO\ApiResponseDto;

class ApiResponseTransformer
{
    public function transform(array $response): ApiResponseDto
    {
        return ApiResponseDto::fromArray($response);
    }
}
