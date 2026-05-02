<?php

namespace Core\Contracts;

/**
 * Contract for symmetric encryption / decryption of sensitive strings.
 *
 * Services depend on this interface rather than on CI's concrete `Crypt` library,
 * which allows unit tests to inject a `FakeCrypt` without a CI runtime.
 */
interface CryptInterface
{
    /** Encrypt a plaintext string and return the ciphertext. */
    public function encode(string $value): string;

    /** Decrypt a ciphertext string and return the plaintext. */
    public function decode(string $value): string;
}
