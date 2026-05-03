<?php

namespace Core\Enums\LetsPeppol;

/**
 * Peppol invoice status enum.
 *
 * Represents invoice-specific states in the Peppol network.
 * Extends beyond transmission status to include business logic states.
 */
enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';
    case UNDER_REVIEW = 'under_review';
}
