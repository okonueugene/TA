<?php

namespace App\Http\Livewire\Admin;

use App\Models\Site;
use Livewire\Component;
use App\Models\SiteSettings;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

class Sites extends Component
{
    use WithPagination;
    use WithFileUploads;
    protected $paginationTheme = 'bootstrap';


    public $name;
    public $email;
    public $logo;
    public $maintenance_mode;
    public $copyright;


    public function updateSiteSettings()
    
    {

        $this->validate([
            'name' => 'nullable',
            'email' => 'nullable|email',
            'logo' => 'nullable|image|max:1024',
            'maintenance_mode' => 'nullable',
            'copyright' => 'nullable',
        ]);

        $site = SiteSettings::first();
        if ($site == null) {
        //create site settings
        $settings = new SiteSettings();
        $settings->name = $this->name;
        $settings->email = $this->email;
        $settings->logo = $this->logo;
        $settings->maintenance_mode = $this->maintenance_mode;
        $settings->copyright = $this->copyright;

        if ($this->logo) {
            $this->logo->storeAs('public', time() . '.' . $this->logo->extension());
            $settings->logo = time() . '.' . $this->logo->extension();
        }

        $settings->save();
        }
        else{
            //update site settings
        $settings = SiteSettings::first();
        $settings->name = $this->name ?? $settings->name;
        $settings->email = $this->email ?? $settings->email;
        $settings->logo = $this->logo ?? $settings->logo;
        $settings->maintenance_mode = $this->maintenance_mode ?? $settings->maintenance_mode;
        $settings->copyright = $this->copyright ?? $settings->copyright;

        if ($this->logo) {
            dd($this->logo);    
            $this->logo->storeAs('public', time() . '.' . $this->logo->extension());
            $settings->logo = time() . '.' . $this->logo->extension();
        }

        $settings->save();
        }   


        
        return redirect()->back()->with('success', 'Site Settings Updated Successfully');
    }




    public function render()
    {
        $title = "Site Settings";
        $settings = SiteSettings::first();
        $user = User::where('id', Auth::user()->id)->first();
        return view('livewire.admin.site', compact('title', 'settings','user'))->extends('layouts.admin', ['title' => $title])->section('content');


    }
}
