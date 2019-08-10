# ConfigCat Sample App for Laravel
https://configcat.com

ConfigCat SDK for PHP provides easy integration for your application to ConfigCat.

ConfigCat is a feature flag and configuration management service that lets you separate releases from deployments. You can turn your features ON/OFF using <a href="https://app.configcat.com" target="_blank">ConfigCat Management Console</a> even after they are deployed. ConfigCat lets you target specific groups of users based on region, email or any other custom user attribute.

ConfigCat is a <a href="https://configcat.com" target="_blank">hosted feature flag service</a>. Manage feature toggles across frontend, backend, mobile, desktop apps. <a href="https://configcat.com" target="_blank">Alternative to LaunchDarkly</a>. Management app + feature flag SDKs.

## Getting started

### 1. Install the package with [Composer](https://getcomposer.org/)
```shell
composer require configcat/configcat-client
```

### 2. Register ConfigCatClient in `app/Providers/AppServiceProvider.php`
```php
<?php
// ...
use ConfigCat\Cache\LaravelCache;
use ConfigCat\ConfigCatClient;
// ....
class AppServiceProvider extends ServiceProvider
{
    // ...    
    public function register()
    {
        $this->app->singleton('ConfigCat\ConfigCatClient', function () {
            return new ConfigCatClient("#YOUR-API-KEY#", [
                'cache' => new LaravelCache(Cache::store()),
                'cache-refresh-interval' => 5
            ]);
        });
    }
    // ...
```

### 3. Use ConfigCatClient in your Controllers
```php
<?php
// ...
use ConfigCat\ConfigCatClient;
use ConfigCat\User;
//...
class MyController extends Controller
{
    private $configCatClient;
    
    public function __construct(ConfigCatClient $configCatClient)
    {
        $this->configCatClient = $configCatClient;
    }
    
    public function index()
    {
        return $this->configCatClient->getValue("keySampleText", null, new User("id"));
    }
    // ...
```
