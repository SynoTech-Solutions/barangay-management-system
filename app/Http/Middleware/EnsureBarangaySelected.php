<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentBarangay;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureBarangaySelected
{
    public function __construct(private CurrentBarangay $currentBarangay) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentBarangay->require();

        return $next($request);
    }
}
