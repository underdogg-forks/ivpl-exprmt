<?php

namespace Core\Enums\LetsPeppol;

/**
 * Peppol receipt type enum.
 *
 * Represents types of receipt acknowledgments in the Peppol network.
 * Based on UBL Application Response specifications.
 */
enum ReceiptType: string
{
    case APPLICATION_RESPONSE = 'application_response';
    case MESSAGE_LEVEL_RESPONSE = 'message_level_response';
    case DELIVERY_NOTIFICATION = 'delivery_notification';
    case READ_RECEIPT = 'read_receipt';
}
