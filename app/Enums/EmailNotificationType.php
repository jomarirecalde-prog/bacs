<?php

namespace App\Enums;

enum EmailNotificationType: string
{
    case AccountCreated = 'account_created';
    case AccountUpdated = 'account_updated';
    case TransactionApproved = 'transaction_approved';
    case TransactionRejected = 'transaction_rejected';
}
