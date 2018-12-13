<?php

namespace Vin7ent\Kj1688;

use Illuminate\Support\ServiceProvider;

class AliKJServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'./config/config.php' => config_path('alikj.php'),
        ], 'config');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('alikj',function (){
           return new AliKJ;
        });
    }
}
