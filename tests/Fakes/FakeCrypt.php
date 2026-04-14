<?php

namespace Tests\Fakes;

use App\Contracts\CryptInterface;

/**
 * Transparent fake for CryptInterface.
 *
 * Encodes with base64 (decode reverses it) so that encrypted values are
 * deterministic and the round-trip is intact — without needing a real key.
 *
 * Usage:
 *   $crypt = new FakeCrypt();
 *   $encoded = $crypt->encode('secret');   // "c2VjcmV0" (base64)
 *   $crypt->decode($encoded);              // "secret"
 */
class FakeCrypt implements CryptInterface
{
    public function encode(string $value): string
    {
        return base64_encode($value);
    }

    public function decode(string $value): string
    {
        return base64_decode($value);
    }
}
