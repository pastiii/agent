<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Support\TraceService;

class TraceServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     * @author LaravelAcademy.org
     */
    public function register()
    {
        //使用singleton绑定单例
        $this->app->singleton('Trace',function(){
            return new TraceService();
        });

    }
}