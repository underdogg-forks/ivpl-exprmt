<?php

namespace Core\Enums\LetsPeppol;

/**
 * Peppol credit note status enum.
 *
 * Represents credit note-specific states in the Peppol network.
 * Mirrors invoice status but specific to credit note lifecycle.
 */
enum CreditNoteStatus: string
{
    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';
}
