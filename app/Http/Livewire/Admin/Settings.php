<?php

namespace App\Http\Livewire\Admin;

use App\Models\Site;
use Livewire\Component;

class Settings extends Component
{
    public $name;
    public $email;
    public $logo;
    public $company_id;

    public function clearInput()
    {
        $this->name = "";
        $this->email = "";
    }
    public function showCompany()
    {
        $site = Site::first()->get();
        $this->name = $site->company_name;
        $this->email = $site->email;
        $this->logo = $site->logo;
        $this->company_id = $site->id;
    }

    public function updateCompany()
    {
        $site = Site::first()->get();

        $this->validate([
            'name' => 'required',
            'email' => 'required|email|string|unique:sites,company_email,' . $site->id
        ]);

        $site->update([
            'company_name' => $this->name,
            'company_email' => $this->email,
        ]);

        $this->dispatchBrowserEvent('success', [
            'message' => 'Site updated successfully',
        ]);

        $this->emit('userStore');

        $this->clearInput();
    }

    public function render()
    {
        $title = "Settings";
        $sites = Site::orderBy('id', 'ASC')->first()->get();
        return view('livewire.admin.settings', compact('sites')) ->extends('layouts.admin', ['title' => $title])
        ->section('content');
    }
}
