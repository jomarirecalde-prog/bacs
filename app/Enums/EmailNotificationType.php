<?php

namespace App\Enums;

enum EmailNotificationType: string
{
    case AccountCreated = 'account_created';
    case TransactionApproved = 'transaction_approved';
    case TransactionRejected = 'transaction_rejected';
}
