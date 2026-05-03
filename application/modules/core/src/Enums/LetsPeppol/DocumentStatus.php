<?php

namespace Core\Enums\LetsPeppol;

/**
 * Peppol document status enum.
 *
 * Represents lifecycle states of a document in the Peppol network.
 * Covers states from creation through archival.
 */
enum DocumentStatus: string
{
    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case ARCHIVED = 'archived';
    case EXPIRED = 'expired';
}
