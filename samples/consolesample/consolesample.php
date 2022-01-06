<?php

require __DIR__ . '/vendor/autoload.php';

$client = new \ConfigCat\ConfigCatClient("PKDVCLf-Hq-h-kCzMp-L7Q/HhOWfwVtZ0mb30i9wi17GQ", [
    \ConfigCat\ClientOptions::LOGGER => new \Monolog\Logger("consolesample"),
    // Info level logging helps to inspect the feature flag evaluation process.
    // Use the default Warning level to avoid too detailed logging in your application.
    \ConfigCat\ClientOptions::LOG_LEVEL => \ConfigCat\Log\LogLevel::INFO,
]);

$user = new \ConfigCat\User("Some UserID",
    "configcat@example.com", "Awesomnia", [ "version" => "1.0.0" ]
);

$value = $client->getValue('isPOCFeatureEnabled', false, $user);
var_dump($value);
