<?php

use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\Log\LogLevel;
use ConfigCat\User;
use Monolog\Logger;

require __DIR__ . '/vendor/autoload.php';

$client = new ConfigCatClient(
    'PKDVCLf-Hq-h-kCzMp-L7Q/HhOWfwVtZ0mb30i9wi17GQ',
    [
        ClientOptions::LOGGER => new Logger('consolesample'),
        // Info level logging helps to inspect the feature flag evaluation process.
        // Use the default Warning level to avoid too detailed logging in your application.
        ClientOptions::LOG_LEVEL => LogLevel::INFO,
    ]
);

$user = new User(
    'Some UserID',
    'configcat@example.com',
    'Awesomnia',
    [ 'version' => '1.0.0' ]
);

$value = $client->getValue('isPOCFeatureEnabled', false, $user);
var_dump($value);
