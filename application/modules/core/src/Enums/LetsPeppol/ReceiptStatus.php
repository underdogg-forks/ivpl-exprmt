<?php

namespace Core\Enums\LetsPeppol;

/**
 * Peppol receipt status enum.
 *
 * Represents the status codes in UBL Application Response.
 * Based on Peppol specification for document acceptance/rejection.
 */
enum ReceiptStatus: string
{
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CONDITIONALLY_ACCEPTED = 'conditionally_accepted';
    case UNDER_REVIEW = 'under_review';
}
