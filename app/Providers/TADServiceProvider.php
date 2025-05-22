<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TADServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        
        require_once base_path('vendor/custom/tad-php/lib/TADFactory.php');
        require_once base_path('vendor/custom/tad-php/lib/TAD.php');
        require_once base_path('vendor/custom/tad-php/lib/TADResponse.php');
        require_once base_path('vendor/custom/tad-php/lib/Providers/TADSoap.php');
        require_once base_path('vendor/custom/tad-php/lib/Providers/TADZKLib.php');
        require_once base_path('vendor/custom/tad-php/lib/Exceptions/ConnectionError.php');
        require_once base_path('vendor/custom/tad-php/lib/Exceptions/FilterArgumentError.php');
        require_once base_path('vendor/custom/tad-php/lib/Exceptions/UnrecognizedArgument.php');
        require_once base_path('vendor/custom/tad-php/lib/Exceptions/UnrecognizedCommand.php');

        $this->app->singleton('tad', function ($app) {
            $config = $app['config']['tad'];
            $factory = new \TADPHP\TADFactory($config);
            return $factory->get_instance();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
