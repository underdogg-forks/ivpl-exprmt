<?php

namespace Core\Enums\LetsPeppol;

/**
 * Peppol document type enum.
 *
 * Represents standard Peppol/UBL document types according to
 * Peppol BIS (Business Interoperability Specifications).
 */
enum DocumentType: string
{
    case INVOICE = 'invoice';
    case CREDIT_NOTE = 'credit_note';
    case ORDER = 'order';
    case ORDER_RESPONSE = 'order_response';
    case DESPATCH_ADVICE = 'despatch_advice';
    case ORDER_AGREEMENT = 'order_agreement';
    case CATALOGUE = 'catalogue';
    case APPLICATION_RESPONSE = 'application_response';
    case MESSAGE_LEVEL_RESPONSE = 'message_level_response';
}
