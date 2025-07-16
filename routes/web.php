<?php

use App\Http\Livewire\Admin\Sites;
use App\Http\Livewire\Admin\Profile;
use Illuminate\Support\Facades\Auth;
use App\Http\Livewire\Admin\Holidays;
use Illuminate\Support\Facades\Route;
use App\Http\Livewire\Admin\Dashboard;
use App\Http\Livewire\Admin\Departments;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ClocksController;
use App\Http\Controllers\Admin\ShiftsController;
use App\Http\Controllers\Admin\EmployeesController;
use App\Http\Controllers\Admin\AttendanceController;

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
Route::get('admin/activity', function () {
    return view('livewire.admin.loginactivity', with(['title' => 'Login Activity']));
})->name('activity');
Route::group(['middleware' => 'auth'], function () {
    Route::group(
        [
            'prefix'     => 'admin',
            'middleware' => 'admin',
            'as'         => 'admin.',
        ],
        function () {
            Route::get('/dashboard', Dashboard::class)->name('admin-dashboard');
            Route::get('/settings', Sites::class)->name('admin-site');
            Route::get('/holidays', Holidays::class)->name('admin-holidays');
            Route::get('/depatments', Departments::class)->name('admin-departments');
            Route::get('/profile', Profile::class)->name('admin-profile');
            Route::resource('/employees', EmployeesController::class);
            Route::post('/employees/check-pin', [EmployeesController::class, 'checkPin'])->name('employees.check-pin');
            Route::get('/clocks/export', [ClocksController::class, 'export'])->name('clocks.export');
            Route::resource('/clocks', ClocksController::class);
            Route::resource('/attendance', AttendanceController::class);
            Route::get('/attendances/export', [AttendanceController::class, 'export'])->name('attendance.export');
            Route::resource('/shifts', ShiftsController::class);
            Route::resource('/users', UserController::class);
            Route::resource('/logs', LogsController::class);
        }
    );
});

