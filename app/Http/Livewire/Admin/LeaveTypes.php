<?php

namespace App\Http\Livewire\Admin;

use Livewire\Component;
use App\Models\LeaveType;
use Livewire\WithPagination;

class LeaveTypes extends Component
{

    use WithPagination;
    protected $paginationTheme = 'bootstrap';

    public $name;
    public $description;
    public $duration;
    public $search = '';


    public function clearInput()
    {
        $this->name = "";
        $this->description = "";
        $this->duration = "";
    }
    public function addLeaveType()
    {
        $this->validate([
            'name' => 'required',
            'description' => 'required',
            'duration' => 'required'
        ]);

        LeaveType::create([
            'name' => $this->name,
            'description' => $this->description,
            'duration' => $this->duration
        ]);

        $this->dispatchBrowserEvent('success', [
            'message' => 'Leave Type Added successfully',
        ]);

        $this->clearInput();
        $this->emit('userStore');
    }
    public function deleteLeaveType(LeaveType $leavetype)
    {
        $leavetype->delete();

        $this->dispatchBrowserEvent('success', [
            'message' => 'Leave Type deleted successfully',
        ]);
    }
    public function render()
    {
        $title = "Leave Types";
        $leavetypes = Leavetype::orderBy('id' , 'ASC')->where('name', 'like', '%'.$this->search.'%')->paginate(4);
        return view('livewire.admin.leave-type', compact('leavetypes'))
        ->extends('layouts.admin',['title'=> $title])
        ->section('content');
    }
}
