<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {

        //user_types: admin, employee, gm,manager
        $user_type = auth()->user()->user_type;
        if($user_type == 'admin') {
            return redirect()->route('admin.admin-dashboard');
        } elseif($user_type == 'employee') {
            return redirect()->route('employee.employee-dashboard');
        } elseif($user_type == 'gm') {
            return redirect()->route('gm.gm-dashboard');
        } elseif($user_type == 'manager') {
            return redirect()->route('manager.manager-dashboard');
        } else {
            return redirect()->route('login');
        }
    }
}
