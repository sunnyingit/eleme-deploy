<?php


return array(
    'GITHUB_ORGANIZATION' => '',
    'HOST_URL' => '',

    'GITHUB_CLIENT_ID' => '',
    'GITHUB_CLIENT_SECRET' => '',
    'CLIENT_PROXY' => '',

    'SUPER_USERS' => array(),

    'SMTP_HOST' => '',
    'SMTP_PORT' => 222,
    'SMTP_ENCRYPTION' => 'ssl',
    'SMTP_FROM_ADDRESS' => '',
    'SMTP_FROM_NAME' => '',
    'SMTP_USER' => '',
    'SMTP_PASS' => '',

    'DEPLOY_PERMISSIONS' => array(
        //"hostype" => "能发布的team"
        'testing' => 'QA',
    ),

    // allow dir
    'REMOTE_DIR_PREFIX' => '/var/www',
);
