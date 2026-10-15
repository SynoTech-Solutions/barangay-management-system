<?php

namespace App\Http\Controllers;

use App\Enums\LogEvent;
use App\Enums\ReadinessCheck;
use App\Enums\ReadinessStatus;
use App\Support\CacheKeys;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HealthController extends Controller
{
    private const int PROBE_TTL_SECONDS = 10;

    public function ready(Cache $cache): JsonResponse
    {
        $checks = [
            ReadinessCheck::Database->value => $this->probe(fn () => DB::select('select 1')),
            ReadinessCheck::Cache->value => $this->probe(function () use ($cache): void {
                $cache->put(CacheKeys::readinessProbe(), true, self::PROBE_TTL_SECONDS);
            }),
        ];

        $healthy = ! in_array(false, $checks, true);

        if (! $healthy) {
            Log::error(LogEvent::ReadinessCheckFailed->value, $checks);
        }

        return response()->json(
            ['status' => ($healthy ? ReadinessStatus::Ok : ReadinessStatus::Unavailable)->value, 'checks' => $checks],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    private function probe(callable $check): bool
    {
        try {
            $check();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
