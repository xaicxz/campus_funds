<?php
// XAMPP local defaults. Configure strong credentials for any deployed environment.
return [
    'host' => getenv('CF_DB_HOST') ?: '127.0.0.1',
    'name' => getenv('CF_DB_NAME') ?: 'campus_funds_db',
    'user' => getenv('CF_DB_USER') ?: 'root',
    'pass' => getenv('CF_DB_PASS') ?: '',
];
