<?php

namespace App\Providers;

use App\Models\User;
use App\Models\SiteSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Database\QueryException; // Import the specific exception
use Illuminate\Support\Facades\Log; // Import Log facade

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
        // Global View Composer for company_name
        View::composer('*', function ($view) {
            $companyName = []; // Default empty array

            try {
                // Attempt to fetch site settings from the database
                $settings = SiteSettings::pluck('name');
                // Check if pluck returned a collection before converting to array
                $companyName = $settings ? $settings->toArray() : [];

            } catch (QueryException $e) {
                // If a database query exception occurs (like connection refused),
                // log the error but continue without failing.
                Log::error("Database connection failed in ComposerServiceProvider while fetching site settings: " . $e->getMessage());
                // Provide a fallback name or keep empty if database is down
                $companyName = ['Your Company Name']; // Use a default name
            } catch (\Exception $e) {
                 // Catch any other potential exceptions during the process
                 Log::error("An unexpected error occurred in ComposerServiceProvider: " . $e->getMessage());
                 // Provide a fallback name or keep empty
                 $companyName = ['Your Company Name']; // Use a default name
            }

            // Ensure $companyName is always an array, even if the query returned null/empty
             if (!is_array($companyName)) {
                 $companyName = [];
             }


            $view->with('company_name', $companyName);
        });

        // View Composer for general manager login activity
        View::composer('livewire.general-manager.loginactivity', function ($new) {
            $activities = []; // Default empty array
            try {
                 // Check if Auth::user() exists before trying to access id and authentications
                 if (Auth::check()) {
                     $user = User::find(Auth::user()->id);
                     // Check if user exists and has the authentications relationship
                     $activities = $user && $user->authentications ? $user->authentications : [];
                 }
            } catch (QueryException $e) {
                 Log::error("Database connection failed in ComposerServiceProvider while fetching user authentications: " . $e->getMessage());
                 // Continue with empty activities
            } catch (\Exception $e) {
                 Log::error("An unexpected error occurred in ComposerServiceProvider: " . $e->getMessage());
                 // Continue with empty activities
            }
            $new->with('activities', $activities);
        });

        // View Composer for admin login activity
        View::composer('livewire.admin.loginactivity', function ($new) {
             $activities = []; // Default empty array
             try {
                 // Check if Auth::user() exists before trying to access id and authentications
                 if (Auth::check()) {
                     $user = User::find(Auth::user()->id);
                      // Check if user exists and has the authentications relationship
                     $activities = $user && $user->authentications ? $user->authentications : [];
                 }
             } catch (QueryException $e) {
                 Log::error("Database connection failed in ComposerServiceProvider while fetching admin authentications: " . $e->getMessage());
                 // Continue with empty activities
             } catch (\Exception $e) {
                 Log::error("An unexpected error occurred in ComposerServiceProvider: " . $e->getMessage());
                 // Continue with empty activities
             }
            $new->with('activities', $activities);
        });
    }
}
