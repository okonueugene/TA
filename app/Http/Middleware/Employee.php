<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Employee
{
    public function handle(Request $request, Closure $next)
    {
        if(auth()->user()->user_type !='employee'){
            abort(403,'Unauthorized action.');
        }
        return $next($request);
    }
}

