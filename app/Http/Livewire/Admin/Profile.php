<?php
namespace App\Http\Livewire\Admin;
use App\Models\User;
use Livewire\Component;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Support\Facades\Auth;

class Profile extends Component
{
    public function render()
    {
        $title="Profile";
        $user=User::where('id', Auth::user()->id)->first();
        return view('livewire.admin.profile' ,compact('user'))
        ->extends('layouts.admin',['title'=> $title])
        ->section('content');
    }
}
