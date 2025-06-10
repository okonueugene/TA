<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TADServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        $this->registerTadLibrary();

        $this->app->singleton('tad', function ($app) {
            $config = $app['config']['tad'];

            $this->validateConfig($config);

            try {
                $factory = new \TADPHP\TADFactory($config);
                $instance = $factory->get_instance();

                Log::info('TAD instance created successfully.');
                return $instance;
            } catch (\Exception $e) {
                Log::error('Failed to initialize TAD instance: ' . $e->getMessage());
                throw new InvalidArgumentException('TAD device connection failed.');
            }
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

    /**
     * Register required library files.
     */
    protected function registerTadLibrary()
    {
        $files = [
            'TADFactory.php',
            'TAD.php',
            'TADResponse.php',
            'Providers/TADSoap.php',
            'Providers/TADZKLib.php',
            'Exceptions/ConnectionError.php',
            'Exceptions/FilterArgumentError.php',
            'Exceptions/UnrecognizedArgument.php',
            'Exceptions/UnrecognizedCommand.php',
        ];

        foreach ($files as $file) {
            $path = base_path("vendor/custom/tad-php/lib/{$file}");
            if (!file_exists($path)) {
                Log::error("TAD library file missing: {$file}");
                throw new \RuntimeException("TAD library file missing: {$file}");
            }
            require_once $path;
        }
    }

    /**
     * Validate essential configuration.
     */
    protected function validateConfig(array $config)
    {
        if (!filter_var($config['ip'], FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException("Invalid TAD IP address: {$config['ip']}");
        }

        if (!is_numeric($config['com_key']) || $config['com_key'] < 0) {
            throw new InvalidArgumentException("Invalid COM key in TAD config.");
        }

        if (!is_numeric($config['soap_port']) || $config['soap_port'] < 1) {
            throw new InvalidArgumentException("Invalid SOAP port in TAD config.");
        }

        if (!is_numeric($config['udp_port']) || $config['udp_port'] < 1) {
            throw new InvalidArgumentException("Invalid UDP port in TAD config.");
        }
    }
}
