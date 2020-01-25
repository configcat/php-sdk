<?php

require __DIR__ . '/vendor/autoload.php';

$client = new \ConfigCat\ConfigCatClient("PKDVCLf-Hq-h-kCzMp-L7Q/HhOWfwVtZ0mb30i9wi17GQ", [
    'logger' => new \Monolog\Logger("consolesample"),
]);

$user = new \ConfigCat\User("Some UserID",
    "configcat@example.com", "Awesomnia", [ "version" => "1.0.0"]
);

$value = $client->getValue('isPOCFeatureEnabled', false, $user);
var_dump($value);
