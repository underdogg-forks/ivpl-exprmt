<?php

namespace Core\Gateways\LetsPeppol\DTO;

class ApiResponseDto
{
    private string $status = '';
    private string $id = '';
    private string $message = '';
    private array $payload = [];

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->setStatus((string) ($data['status'] ?? ''));
        $dto->setId((string) ($data['id'] ?? ($data['transmission_id'] ?? '')));
        $dto->setMessage((string) ($data['message'] ?? ''));
        $dto->setPayload($data);

        return $dto;
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function getId(): string { return $this->id; }
    public function setId(string $id): void { $this->id = $id; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): void { $this->message = $message; }
    public function getPayload(): array { return $this->payload; }
    public function setPayload(array $payload): void { $this->payload = $payload; }
}
