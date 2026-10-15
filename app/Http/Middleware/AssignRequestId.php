<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditTrail;
use App\Support\Config\BmsSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class AssignRequestId
{
    private const int MAX_INBOUND_LENGTH = 64;

    private const string ALLOWED_PATTERN = '/^[A-Za-z0-9._-]+$/';

    public function __construct(private BmsSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $this->settings->requestIdHeader;
        $inbound = $request->headers->get($header);

        $requestId = is_string($inbound)
            && strlen($inbound) <= self::MAX_INBOUND_LENGTH
            && preg_match(self::ALLOWED_PATTERN, $inbound) === 1
                ? $inbound
                : (string) Str::uuid();

        Context::add(AuditTrail::REQUEST_ID_CONTEXT_KEY, $requestId);

        $response = $next($request);
        $response->headers->set($header, $requestId);

        return $response;
    }
}
