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
        if($user_type == 'admin' || $user_type == 'manager') {
            return redirect()->route('admin.admin-dashboard');
        }  else {
            return redirect()->route('login');
        }
    }

    public function activity()
    {
        $activity = activity()->all();
        return view('admin.activity');
    }
}
