<?php

namespace Core\Enums\LetsPeppol;

/**
 * Peppol transmission error code enum.
 *
 * Represents common error codes that can occur during Peppol transmission.
 * Based on Peppol AS4 specification and common network errors.
 */
enum ErrorCode: string
{
    case INVALID_RECIPIENT = 'INVALID_RECIPIENT';
    case INVALID_DOCUMENT = 'INVALID_DOCUMENT';
    case SCHEMA_VALIDATION_ERROR = 'SCHEMA_VALIDATION_ERROR';
    case RECIPIENT_UNAVAILABLE = 'RECIPIENT_UNAVAILABLE';
    case TIMEOUT = 'TIMEOUT';
    case AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';
    case AUTHORIZATION_FAILED = 'AUTHORIZATION_FAILED';
    case NETWORK_ERROR = 'NETWORK_ERROR';
    case DUPLICATE_DOCUMENT = 'DUPLICATE_DOCUMENT';
    case UNSUPPORTED_DOCUMENT_TYPE = 'UNSUPPORTED_DOCUMENT_TYPE';
    case INVALID_FORMAT = 'INVALID_FORMAT';
    case SIGNATURE_VERIFICATION_FAILED = 'SIGNATURE_VERIFICATION_FAILED';
}
