<?php

use App\Http\Livewire\Admin\Profile;
use Illuminate\Support\Facades\Auth;
use App\Http\Livewire\Admin\Holidays;
use App\Http\Livewire\Admin\Settings;
use Illuminate\Support\Facades\Route;
use App\Http\Livewire\Admin\Sites;
use App\Http\Livewire\Admin\Dashboard;
use App\Http\Livewire\Admin\Employees;
use App\Http\Livewire\Admin\ApplyLeave;
use App\Http\Livewire\Admin\LeaveTypes;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\EmployeesController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\ShiftsController;
use App\Http\Livewire\Admin\Departments;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});




Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/activity', function () {
    return view('livewire.admin.loginactivity', with(['title' => 'Login Activity']));
})->name('activity');
Route::get('/activities', function () {
    return view('livewire.general-manager.loginactivity', with(['title' => 'Login Activity']));
})->name('activities');
Route::group(['middleware' => 'auth'], function () {
    Route::group(
        [
        'prefix' => 'admin',
        'middleware' => 'admin',
        'as' => 'admin.'
    ],
        function () {
            Route::get('/dashboard', Dashboard::class)->name('admin-dashboard');
            Route::get('/settings', Sites::class)->name('admin-site');
            Route::get('/holidays', Holidays::class)->name('admin-holidays');
            Route::get('/depatments', Departments::class)->name('admin-departments');
            Route::get('/profile', Profile::class)->name('admin-profile');
            Route::resource('/employees', EmployeesController::class);
            Route::resource('/attendance' , AttendanceController::class);
            Route::resource('/shifts' , ShiftsController::class);
            Route::get('/attendances/export', [AttendanceController::class, 'export'])->name('attendance.export');

        }
    );
    
});