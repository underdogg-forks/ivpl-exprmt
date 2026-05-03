<?php

namespace Core\Enums\LetsPeppol;

/**
 * Peppol participant capability enum.
 *
 * Represents capabilities/document types that a Peppol participant can handle.
 * Based on Peppol BIS (Business Interoperability Specifications).
 */
enum ParticipantCapability: string
{
    case INVOICE = 'invoice';
    case CREDIT_NOTE = 'credit_note';
    case ORDER = 'order';
    case ORDER_RESPONSE = 'order_response';
    case DESPATCH_ADVICE = 'despatch_advice';
    case ORDER_AGREEMENT = 'order_agreement';
    case CATALOGUE = 'catalogue';
    case APPLICATION_RESPONSE = 'application_response';
}
