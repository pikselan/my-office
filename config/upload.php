<?php

use App\Core\Config;

return [
    'max_size' => (int) Config::env('UPLOAD_MAX_SIZE', 5242880),
    'allowed_types' => array_values(array_filter(array_map('trim',
        explode(',', (string) Config::env('UPLOAD_ALLOWED_TYPES', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx,csv'))
    ))),
    'storage_path' => 'storage/private',
];
