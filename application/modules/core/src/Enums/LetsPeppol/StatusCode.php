<?php

namespace Core\Enums\LetsPeppol;

/**
 * Peppol UBL status code enum.
 *
 * Represents status codes used in UBL Application Response documents.
 * AP = Accepted, RE = Rejected, AB = Acknowledged, CA = Conditionally Accepted.
 * Based on ISO/IEC 19845 (UBL) specification.
 */
enum StatusCode: string
{
    case AP = 'AP';  // Accepted
    case RE = 'RE';  // Rejected
    case AB = 'AB';  // Acknowledged
    case CA = 'CA';  // Conditionally Accepted
    case IP = 'IP';  // In Process
    case UQ = 'UQ';  // Under Query
}
