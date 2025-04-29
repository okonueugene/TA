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

        }
    );
    // Route::group(
    //     [
    //     'prefix' => 'employee',
    //     'middleware' => 'employee',
    //     'as' => 'employee.'

    // ],
    //     function () {
    //         Route::get('/dashboard', EmployeeDashboard::class)->name('employee-dashboard');
    //         Route::get('/holidays', EmployeeHoliday::class)->name('employee-holidays');
    //         Route::get('/profile', EmployeeProfile::class)->name('employee-profile');
    //         Route::get('/apply-leave', EmployeeApplyLeave::class)->name('employee-apply-leave');
    //         Route::get('/approved-leave', EmployeeApprovedLeave::class)->name('employee-approved-leave');
    //         Route::get('/rejected-leave', EmployeeRejectedLeave::class)->name('employee-rejected-leave');
    //     }
    // );
    // Route::group(
    //     [
    //     'prefix' => 'manager',
    //     'middleware' => 'manager',
    //     'as' => 'manager.'

    // ],
    //     function () {
    //         Route::get('/dashboard', ManagerDashboard::class)->name('manager-dashboard');
    //         Route::get('/holidays', ManagerHolidays::class)->name('manager-holidays');
    //         Route::get('/departments', ManagerDepartments::class)->name('manager-departments');
    //         Route::get('/leave-types', ManagerLeaveTypes::class)->name('manager-leave-types');
    //         Route::get('/employees', ManagerEmployees::class)->name('manager-employees');
    //         Route::get('/profile', ManagerProfile::class)->name('manager-profile');
    //         Route::get('/manage-leaves', ManagerManageLeave::class)->name('manager-manage-leave');
    //         Route::get('/apply-leave', ManagerApplyLeave::class)->name('manager-apply-leave');
    //         Route::get('/approved-leave', ManagerApprovedLeave::class)->name('manager-approved-leave');
    //         Route::get('/rejected-leave', ManagerRejectedLeave::class)->name('manager-rejected-leave');
    //     }
    // );
    // Route::group(
    //     [
    //     'prefix' => 'general',
    //     'middleware' => 'general manager',
    //     'as' => 'gm.'

    // ],
    //     function () {
    //         Route::get('/dashboard', GmDashboard::class)->name('gm-dashboard');
    //         Route::get('/holidays', GmHolidays::class)->name('gm-holidays');
    //         Route::get('/departments', GmDepartment::class)->name('gm-departments');
    //         Route::get('/leave-types', GmLeaveTypes::class)->name('gm-leave-types');
    //         Route::get('/apply-leaves', GmApplyLeave::class)->name('gm-apply-leaves');
    //         Route::get('/approved-leave', GmApprovedLeave::class)->name('gm-approved-leave');
    //         Route::get('/rejected-leave', GmRejectedLeave::class)->name('gm-rejected-leave');
    //         Route::get('/manage-leave', GmManageLeave::class)->name('gm-manage-leave');
    //         Route::get('/employees', GmEmployees::class)->name('gm-employees');
    //         Route::get('/profile', GmProfile::class)->name('gm-profile');
    //     }
    // );
});


Route::controller(FullCalenderController::class)->group(function () {
    Route::get('/scheduler', [FullCalenderController::class, 'index'])->name('fullcalender');

    Route::post('/fullcalenderAjax', 'ajax');
});