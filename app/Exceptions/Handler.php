<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\Exception\TransportException;
use Illuminate\Support\Facades\Log;

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
        // Improved handling for TokenMismatchException
        if ($exception instanceof TokenMismatchException) {
            // Log the error for debugging purposes
            Log::notice('CSRF token mismatch for user: ' . 
                ($request->user() ? $request->user()->id : 'guest') . 
                ' on URL: ' . $request->fullUrl());
            
            // Always redirect to login for better security
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Please refresh and sign in again.',
                    'status' => 419
                ], 419);
            }
            
            // Clear session data to ensure clean state
            $request->session()->flush();
            
            // Redirect to login with a clear message
            return redirect()->route('login')
                ->with('status', 'error')
                ->withErrors([
                    'message' => 'Your session has expired or is invalid. Please sign in again to continue.',
                    'auto_refresh' => true
                ]);
        }

        if ($exception instanceof NotFoundHttpException) {
            return back()->withErrors([
                'delayMessage' => 'Page not found. Redirecting in 3 seconds...', 
                'delaySeconds' => 3,
            ]);
        }

        if ($exception instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
            return redirect()->route('login')
                ->withErrors(['message' => 'You are not authorized to access this page. Please log in with appropriate credentials.']);
        }
        
        // Improved handling for TransportException
        if ($exception instanceof TransportException) {
            // Log the email failure
            Log::error('Email sending failed: ' . $exception->getMessage());
            
            // Always redirect to login when email sending fails
            return redirect()->route('login')
                ->with('status', 'error')
                ->withErrors(['message' => 'We encountered an error sending email. Please try again later.']);
        }

        return parent::render($request, $exception);
    }
}