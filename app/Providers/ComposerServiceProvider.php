<?php

namespace App\Providers;

use App\Models\User;
use App\Models\SiteSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class ComposerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('*', function ($view) {
            $view->with('company_name', SiteSettings::pluck('name')->toArray());
        });
        View::composer('livewire.general-manager.loginactivity', function ($new) {
            $new->with('activities', User::find(Auth::user()->id)->authentications);
        });
        View::composer('livewire.admin.loginactivity', function ($new) {
            $new->with('activities', User::find(Auth::user()->id)->authentications);
        });

    }
}
