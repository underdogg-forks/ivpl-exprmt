<?php

namespace Core\Enums\LetsPeppol;

/**
 * Peppol transmission status enum.
 *
 * Represents all possible states of a document transmission through the Peppol network.
 * Based on Peppol specifications and industry standards.
 */
enum TransmissionStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case TIMEOUT = 'timeout';
    case REJECTED = 'rejected';
}
