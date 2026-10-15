<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Exceptions\BmsException;
use App\Services\Features\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureFeatureEnabled
{
    public function __construct(private FeatureFlags $features) {}

    public function handle(Request $request, Closure $next, string ...$flags): Response
    {
        foreach ($flags as $value) {
            $flag = FeatureFlag::from($value);
            if (! $this->features->enabled($flag)) {
                throw BmsException::of(ErrorCode::FeatureDisabled, ['feature' => $flag->value]);
            }
        }

        return $next($request);
    }
}
