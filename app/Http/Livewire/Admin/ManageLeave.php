<?php

namespace App\Http\Livewire\Admin;

use Mail;
use Carbon\Carbon;
use App\Events\Apply;

use App\Models\Leave;
use Livewire\Component;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Mail\ApprovedMail;
use App\Mail\DeclinedMail;
use App\Models\Department;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use App\Exports\ManageLeavesExport;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ManageLeave extends Component
{
    use WithPagination;
    protected $paginationTheme = 'bootstrap';

    public $pages=10;
    public $order='DESC';
    public $search='';

    public $user_id;
    public $leave_id;
    public $user_name;
    public $employee_id;
    public $date_start;
    public $date_end;
    public $nodays;
    public $leave_type_id;
    public $leave_type;
    public $reason;
    public $status;
    public $remarks;
    public $date_posted;
    public $total;


    public function approved()
    {
        $leave=Leave::orderBy('id', 'DESC')->where('status', 'approved')->first();

        Mail::to("versionaskari19@gmail.com")->queue(new ApprovedMail($leave));
    }

    public function declined()
    {
        $leave=Leave::orderBy('id', 'DESC')->where('status', 'declined')->first();

        Mail::to("versionaskari19@gmail.com")->queue(new DeclinedMail($leave));
    }

    public function export()
    {
        return Excel::download(new ManageLeavesExport(), 'users.xlsx');
    }
       public function clearInput()
       {
           $this->status = "";
           $this->remarks = "";
       }


    public function showLeave($id)
    {
        $leave = Leave::where('id', $id)->first();

        $this->user_id = $leave->user_id;
        $this->leave_id = $leave->id;
        $this->user_name = $leave->user->name;
        $this->employee_id = $leave->employee_id;
        $this->date_start = $leave->date_start;
        $this->date_end = $leave->date_end;
        $this->nodays = $leave->nodays;
        $this->leave_type_id = $leave->leave_type_id;
        $this->leave_type = $leave->type->name;
        $this->reason = $leave->reason;
        $this->date_posted = $leave->date_posted;
    }

 public function updateLeave($id)
 {
     $leave = Leave::findOrFail($id);


     $this->validate([
         'status' => 'required',
         'remarks' => 'required',
     ]);

     $leave->update([
         'status' => $this->status,
         'remarks' => $this->remarks,
         'action_date' => Carbon::now()->format('Y/m/d'),
     ]);

     if ($this->status == 'approved' && $leave->type->name =='Annual Leave') {
        $totalTaken=Leave::where('user_id', $leave->user_id)->where('status', 'approved')->where('leave_type_id', 1)->sum('nodays');
         Employee::where('user_id', $leave->user_id)->update(['leave_taken' => $totalTaken,'available_days' => round(date('L') == 1 ? (21 / 366) * (date('z') + 1) : (21 / 365) * (date('z') + 1), 2)-$totalTaken]);
     }

     if ($this->status == 'approved') {
         $this->approved();
         $transactionName = Auth::user()->name;
         Apply::dispatch("{$transactionName} Has Approved Your Leave");
     } else {
         $this->declined();
         $transactionName = Auth::user()->name;
         Apply::dispatch("{$transactionName} Has Declined a Your Leave");
     }

     $this->dispatchBrowserEvent('success', [
         'message' => 'Leave Updated successfully',
         ]);

     $this->clearInput();
     $this->emit('userStore');
 }

   public function leaveEvents()
   {
   }

     public function render()
     {
         $title="Manage Leave";

         $searchString=$this->search;

         $leaves =Leave::orderBy('id', $this->order)->where('status', 'pending')->whereHas('user', function ($query) use ($searchString) {
             $query->where('name', 'like', '%'.$searchString.'%');
         })
         ->with(['user' => function ($query) use ($searchString) {
             $query->where('name', 'like', '%'.$searchString.'%');
         }])->paginate($this->pages);


         return view('livewire.admin.manage-leave', compact('leaves'))
          ->extends('layouts.admin', ['title'=> $title])
          ->section('content')
         ;
     }
}
