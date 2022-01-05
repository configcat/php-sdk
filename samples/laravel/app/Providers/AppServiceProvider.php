<?php

namespace App\Providers;

use ConfigCat\Cache\LaravelCache;
use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('ConfigCat\ConfigCatClient', function () {
            return new ConfigCatClient("PKDVCLf-Hq-h-kCzMp-L7Q/HhOWfwVtZ0mb30i9wi17GQ", [
                ClientOptions::CACHE => new LaravelCache(Cache::store()),
                ClientOptions::CACHE_REFRESH_INTERVAL => 5
            ]);
        });
    }
}
