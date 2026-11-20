<?php

return [
    'owner_email' => env('QUOTE_OWNER_EMAIL'),
    'lookup_key' => env('QUOTE_LOOKUP_KEY'),
    'discovery_url' => env('QUOTE_DISCOVERY_URL'),
    'rush_percent' => 20,
    'services' => [
        'website' => [
            'name' => 'Business Website', 'rate' => 1500000, 'rush' => true,
            'description' => 'Present your services and give customers a clear way to reach you',
            'includes' => ['Home, Services, and Contact pages using your materials', 'Mobile-friendly layout', 'One inquiry form delivered to one inbox', 'HTTPS, form validation, and spam controls', 'Deployment to one agreed host', 'One pre-launch revision and a handover walkthrough'],
        ],
        'system' => [
            'name' => 'Custom System', 'rate' => null, 'rush' => false,
            'description' => 'Give staff a shared place to manage day-to-day work',
            'includes' => ['Workflow and records scope', 'Owner and staff permissions and two-factor sign-in where applicable', 'Deployment and handover scope'],
        ],
        'automation' => [
            'name' => 'Workflow Automation', 'rate' => null, 'rush' => false,
            'description' => 'Automate a repetitive task across tools you use',
            'includes' => ['One defined trigger and action', 'Compatibility review', 'Test run and handover'],
        ],
        'improve' => [
            'name' => 'Fix & Improve', 'rate' => null, 'rush' => false,
            'description' => 'Repair or extend your existing website or system',
            'includes' => ['Review of the existing setup', 'Agreed fix or extension', 'Verification and handover'],
        ],
        'installation' => [
            'name' => 'Setup & Installation', 'rate' => null, 'rush' => false,
            'description' => 'Configure a supported tool or deploy an existing app',
            'includes' => ['Configuration in one agreed account', 'Verification and handover instructions'],
        ],
    ],
    'installations' => [
        'scheduler' => ['name' => 'Booking tool installation', 'rate' => 300000, 'rush' => true, 'description' => 'Connect and test one existing scheduler'],
        'domain' => ['name' => 'Domain setup', 'rate' => null, 'rush' => false, 'description' => 'Connect an existing domain and verify HTTPS'],
        'mailbox' => ['name' => 'Business mailbox setup', 'rate' => null, 'rush' => false, 'description' => 'Configure and test one mailbox'],
        'deployment' => ['name' => 'App deployment', 'rate' => null, 'rush' => false, 'description' => 'Deploy one existing app to the agreed host'],
    ],
    'goals' => [
        'presence' => ['name' => 'Show what my business offers', 'service' => 'website', 'why' => 'A website gives customers one place to find your services and send an inquiry.', 'features' => ['services', 'inquiries']],
        'appointments' => ['name' => 'Take appointments', 'service' => 'website', 'why' => 'A website gives customers a place to request a booking you can confirm.', 'features' => ['appointment_requests', 'reminders']],
        'records' => ['name' => 'Track sales, stock, or records', 'service' => 'system', 'why' => 'A custom system gives staff one place to record sales and manage stock.', 'question' => 'Which records and reports should each staff role see?', 'features' => ['sales_stock', 'staff_access', 'counter_payments']],
        'repetition' => ['name' => 'Automate a repetitive task', 'service' => 'automation', 'why' => 'An automation can pass information between tools when a defined event happens.', 'question' => 'Which tool starts the task, and what should happen next?'],
        'existing' => ['name' => 'Improve what I already have', 'service' => 'improve', 'why' => 'Reviewing the existing setup helps narrow down the fix or extension.', 'question' => 'What should work differently in your existing setup?'],
        'not_sure' => ['name' => 'I’m not sure yet', 'service' => 'website', 'why' => 'A website is a starting point if customers need to find or contact you.', 'question' => 'What would you like customers or staff to do differently?'],
    ],
    'feature_choices' => [
        'services' => 'Publish my services',
        'inquiries' => 'Receive customer inquiries',
        'appointment_requests' => 'Accept appointment requests',
        'reminders' => 'Send booking reminders',
        'sales_stock' => 'Record sales and stock',
        'staff_access' => 'Give staff access',
        'counter_payments' => 'Record payments at the counter',
    ],
    'appointment_answers' => [
        'manual' => ['name' => 'I confirm requests myself', 'service' => 'website', 'why' => 'A website inquiry form can collect requests for you to confirm.'],
        'existing' => ['name' => 'I use a scheduler on an existing website', 'service' => 'installation', 'installation' => 'scheduler', 'why' => 'An installation connects your existing scheduler without adding a website package.'],
        'staff' => ['name' => 'Staff need to manage availability', 'service' => 'system', 'why' => 'A custom system can manage staff calendars and permissions.', 'question' => 'How should staff manage calendars and appointment changes?'],
        'unknown' => ['name' => 'I’m not sure', 'service' => 'website', 'why' => 'Start by collecting appointment requests while I review how you confirm them.', 'question' => 'Who confirms appointments, and where is the confirmed time stored?'],
    ],
    'addons' => [
        'booking' => ['name' => 'Booking integration', 'description' => 'Connect one supported booking tool', 'rate' => 300000, 'rush' => true, 'max' => 1, 'services' => ['website', 'system', 'improve', 'installation']],
        'email' => ['name' => 'Email integration', 'description' => 'Set up a branded confirmation or another defined email workflow', 'rate' => null, 'rush' => false, 'max' => 1],
        'sms' => ['name' => 'SMS integration', 'description' => 'Configure one event such as an appointment reminder', 'rate' => 250000, 'rush' => true, 'max' => 1, 'services' => ['website', 'system', 'automation', 'improve', 'installation']],
        'content' => ['name' => 'Content upload', 'description' => 'Upload supplied products or services by batch', 'rate' => null, 'rush' => false, 'max' => 20, 'unit' => 'Batches', 'services' => ['website', 'system', 'improve']],
        'copy' => ['name' => 'Copywriting', 'description' => 'Write content for selected pages', 'rate' => null, 'rush' => false, 'max' => 20, 'unit' => 'Pages', 'services' => ['website', 'improve']],
    ],
    'months' => [],
    'provider' => [
        'hosting' => 'Hosting and domain renewals, if needed',
        'booking' => 'Booking platform subscription, if applicable',
        'sms' => 'SMS credits and provider usage fees',
    ],
];
