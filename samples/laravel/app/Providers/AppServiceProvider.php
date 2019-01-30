<?php

namespace App\Providers;

use ConfigCat\Cache\LaravelCache;
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
            return new ConfigCatClient("PKDVCLf-Hq-h-kCzMp-L7Q/psuH7BGHoUmdONrzzUOY7A", [
                'cache' => new LaravelCache(Cache::store()),
                'cache-refresh-interval' => 5
            ]);
        });
    }
}
