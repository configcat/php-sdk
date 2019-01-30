# ConfigCat SDK for PHP

ConfigCat SDK for PHP provides easy integration between ConfigCat service and applications using PHP.

ConfigCat is a feature flag, feature toggle, and configuration management service. That lets you launch new features and change your software configuration remotely without actually (re)deploying code. ConfigCat even helps you do controlled roll-outs like canary releases and blue-green deployments.
https://configcat.com

[![Build Status](https://travis-ci.com/configcat/php-sdk.svg?branch=master)](https://travis-ci.com/configcat/php-sdk)
[![Coverage Status](https://img.shields.io/codecov/c/github/ConfigCat/php-sdk.svg)](https://codecov.io/gh/ConfigCat/php-sdk)
[![Latest Stable Version](https://poser.pugx.org/configcat/configcat-client/version)](https://packagist.org/packages/configcat/configcat-client)
[![Total Downloads](https://poser.pugx.org/configcat/configcat-client/downloads)](https://packagist.org/packages/configcat/configcat-client)
[![Latest Unstable Version](https://poser.pugx.org/configcat/configcat-client/v/unstable)](https://packagist.org/packages/configcat/configcat-client)

## Getting started

### 1. Install the package with [Composer](https://getcomposer.org/)
```shell
composer require configcat/configcat-client
```

### 2. <a href="https://configcat.com/Account/Login" target="_blank">Log in to ConfigCat Management Console</a> and go to your *Project* to get your *API Key*:
![API-KEY](https://raw.githubusercontent.com/ConfigCat/java-sdk/master/media/readme01.png  "API-KEY")

### 4. Create the *ConfigCat* client instance
```php
$client = new \ConfigCat\ConfigCatClient("#YOUR-API-KEY#");
```

### 5. Get your setting value:
```php
$isMyAwesomeFeatureEnabled = $client->getValue("isMyAwesomeFeatureEnabled", false);
if(is_bool($isMyAwesomeFeatureEnabled) && $isMyAwesomeFeatureEnabled) {
    doTheNewThing();
} else {
    doTheOldThing();
}
```

## Getting user specific setting values with Targeting
Using this feature, you will be able to get different setting values for different users in your application by passing a `User Object` to the `getValue()` function.

Read more about [Targeting here](https://docs.configcat.com/docs/advanced/targeting/).


## User object
Percentage and targeted rollouts are calculated by the user object you can optionally pass to the configuration requests.
The user object must be created with a **mandatory** identifier parameter which should uniquely identify each user:
```php
$user = new \ConfigCat\User("#USER-IDENTIFIER#"); // mandatory

$isMyAwesomeFeatureEnabled = $client->getValue("isMyAwesomeFeatureEnabled", false, $user);
if(is_bool($isMyAwesomeFeatureEnabled) && $isMyAwesomeFeatureEnabled) {
    doTheNewThing();
} else {
    doTheOldThing();
}
```

## Sample/Demo app
* [Sample Laravel app](https://github.com/ConfigCat/php-sdk/tree/master/samples/laravel)

## Support
If you need help how to use this SDK feel free to to contact the ConfigCat Staff on https://configcat.com. We're happy to help.

## Contributing
Contributions are welcome.

## About ConfigCat
- [Documentation](https://docs.configcat.com)
- [Blog](https://blog.configcat.com)