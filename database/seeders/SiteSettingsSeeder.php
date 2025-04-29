<?php

namespace Database\Seeders;

use App\Models\SiteSettings;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        SiteSettings::create([
            'name' => 'Laravel Livewire',
            'logo' => 'logo.png',
            'email' => 'info@domain,com',
            'maintenance_mode' => '0',
            'copyright' => '2021 Laravel Livewire',
        ]);

    }
}
