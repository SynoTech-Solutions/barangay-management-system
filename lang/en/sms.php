<?php

return [
    'statuses' => [
        'queued' => 'Queued',
        'sending' => 'Sending',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],
    'sources' => [
        'compose' => 'New message',
        'group' => 'Group message',
        'resident-list' => 'Resident list',
        'retry' => 'Retry',
    ],
    'validation' => [
        'body_required' => 'Type a message.',
        'body_too_long' => 'The message may use at most :max text segments.',
        'group_name_taken' => 'A group with this name already exists.',
        'template_title_taken' => 'A template with this title already exists.',
    ],
];
