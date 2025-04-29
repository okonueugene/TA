<?php

namespace App\Http\Livewire\Admin;

use App\Models\Leave;
use Livewire\Component;
use App\Models\LeaveType;
use App\Models\Department;

class RejectedLeave extends Component
{
    public $pages=10;
    public $order='DESC';
    public $search = '';
    public $remarks;

    public function showRemarks($id)
    {
        $leave = Leave::where('id', $id)->first();
        $this->remarks = $leave->remarks;

    }

    public function render()
    {
        $title="Declined Leave Days";
        $searchString=$this->search;

        $leaves = Leave::orderBy('id',$this->order)->where('status', 'declined')->whereHas('user', function ($query) use ($searchString){
            $query->where('name', 'like', '%'.$searchString.'%');
        })
        ->with(['user' => function($query) use ($searchString){
            $query->where('name', 'like', '%'.$searchString.'%');
        }])->paginate($this->pages);
        $departments = Department::orderBy('id','ASC')->get();
        $types=LeaveType::orderBy('id','ASC')->get();

        return view('livewire.admin.rejected-leave',compact('leaves','departments','types'))
        ->extends('layouts.admin', ['title'=> $title])
        ->section('content')
        ;
    }
}
