<?php
// config.php — edit for your server. Environment variables (HM_DB_*) override these.
return [
    'db_host'   => getenv('HM_DB_HOST') ?: 'localhost',
    'db_name'   => getenv('HM_DB_NAME') ?: 'hm_lab_db',
    'db_user'   => getenv('HM_DB_USER') ?: 'root',          // XAMPP/WAMP default. For production use a limited user (see optional_db_user.sql)
    'db_pass'   => getenv('HM_DB_PASS') !== false ? getenv('HM_DB_PASS') : '',
    'timezone'  => 'Asia/Manila',
    // Needed ONCE to create the first custodian account on admin.html. Change it to your own secret.
    'setup_key' => 'change-this-setup-key',
];
