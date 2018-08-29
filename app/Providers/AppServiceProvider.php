<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AdminConfigService;
use App\Services\ApiService;
use App\Services\PromoService;
use App\Token\Token;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //token注册
        $this->app->singleton('Token',function ($app){
            return new Token($app->request);
        });

        //config
        $this->app->singleton('admin_config',function(){
            return new AdminConfigService();
        });
    }



}
