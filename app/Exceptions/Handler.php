<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (NotFoundHttpException $e, $request) {
            if($request->wantsJson()) {
                return response()->json([
                    'message' => 'Object Not Found',
                    'status'  => 404,
                ]);
            }
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof TokenMismatchException) {
            // Improved handling for expired CSRF tokens / sessions
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Please refresh and try again.',
                    'status' => 419
                ], 419);
            }
            
            // Adapt this redirect based on your authentication status
            if (auth()->check()) {
                // If user is logged in, redirect to an appropriate dashboard
                $user_type = auth()->user()->user_type;
                $route = match($user_type) {
                    'admin' => 'admin.admin-dashboard',
                    'employee' => 'employee.employee-dashboard',
                    'gm' => 'gm.gm-dashboard',
                    'manager' => 'manager.manager-dashboard',
                    default => 'dashboard'
                };
                
                return redirect()->route($route)
                    ->withErrors(['message' => 'Your session has expired. Please try again.']);
            } else {
                // If not logged in, redirect to login
                return redirect()->route('login')
                    ->withErrors(['message' => 'Your session has expired. Please log in again.']);
            }
        }

        if ($exception instanceof NotFoundHttpException) {
            return back()->withErrors([
                'delayMessage' => 'Page not found. Redirecting in 3 seconds...', // Your delay message
                'delaySeconds' => 3, // Delay in seconds
            ]);
        }

        if ($exception instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
            $this->renderable(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
                return redirect()->route('dashboard')->withErrors(['message' => 'You are not authorized to access this page.']);
            });
        }
        // Add handling for TransportException here
        if ($exception instanceof \Symfony\Component\Mailer\Exception\TransportException) {
            $user_type = auth()->user()->user_type;
            if($user_type == 'admin') {
                return redirect()->route('admin.admin-dashboard')->withErrors(['message' => 'Email not sent.']);
            } elseif($user_type == 'employee') {
                return redirect()->route('employee.employee-dashboard')->withErrors(['message' => 'Email not sent.']);
            } elseif($user_type == 'gm') {
                return redirect()->route('gm.gm-dashboard')->withErrors(['message' => 'Email not sent.']);
            } elseif($user_type == 'manager') {
                return redirect()->route('manager.manager-dashboard')->withErrors(['message' => 'Email not sent.']);
            } else {
                return redirect()->route('login')->withErrors(['message' => 'Email not sent.']);
            }
        }

        return parent::render($request, $exception);
    }
}
