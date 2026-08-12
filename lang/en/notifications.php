<?php

return [
    'rent' => [
        'upcoming' => "Hi :name,\n\nRent for :unit is due in :days days.\n\nAmount: :amount\nDue date: :date\n\n:invoice_context\n\n:invoice_link",
        'due_today' => "Hi :name,\n\nRent for :unit is due today.\n\nAmount: :amount\n\n:invoice_context\n\n:invoice_link",
        'overdue' => "Hi :name,\n\nRent for :unit is overdue by :days day(s).\n\nAmount: :amount\n\n:invoice_context\n\n:invoice_link",
        'invoice_context' => "Invoice: :reference\nPeriod: :period\nDue date: :date\nOutstanding: :amount",
        'view_invoice' => 'View invoice',
    ],
    'tenant_invitation' => [
        'subject' => 'You have been invited to OpenKos',
        'greeting' => 'Welcome to OpenKos',
        'intro' => 'You have been given access to your tenant portal.',
        'instruction' => 'Set your password to activate your account and access payments, invoices, and lease details.',
        'action' => 'Accept Invitation',
        'outro' => 'If you did not expect this invitation, you may ignore this email.',
    ],
];
