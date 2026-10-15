<?php

use App\Enums\LogEvent;
use App\Exceptions\BmsException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureBarangaySelected;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentBarangay;
use App\Http\Support\Toast;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->prepend(AssignRequestId::class);

        $middleware->web(append: [
            HandleAppearance::class,
            ResolveCurrentBarangay::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveCurrentBarangay::class);

        $middleware->alias([
            'feature' => EnsureFeatureEnabled::class,
            'barangay' => EnsureBarangaySelected::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport(BmsException::class);

        $exceptions->render(function (BmsException $exception, Request $request) {
            $status = $exception->errorCode->httpStatus();
            $message = $exception->userMessage();

            Log::info(LogEvent::DomainErrorRaised->value, ['code' => $exception->errorCode->value, 'status' => $status]);

            if ($request->expectsJson()) {
                return response()->json(['error' => ['code' => $exception->errorCode->value, 'message' => $message]], $status);
            }

            if (! $request->isMethod('GET')) {
                Toast::error($message);

                return back();
            }

            abort($status, $message);
        });
    })->create();
