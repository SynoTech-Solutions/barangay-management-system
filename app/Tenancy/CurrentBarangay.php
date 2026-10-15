<?php

namespace App\Tenancy;

use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\Barangay;
use Closure;

final class CurrentBarangay
{
    private ?Barangay $barangay = null;

    public function set(?Barangay $barangay): void
    {
        $this->barangay = $barangay;
    }

    public function get(): ?Barangay
    {
        return $this->barangay;
    }

    public function id(): ?int
    {
        return $this->barangay?->id;
    }

    public function require(): Barangay
    {
        return $this->barangay ?? throw BmsException::of(ErrorCode::MissingBarangayContext);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(Barangay $barangay, Closure $callback): mixed
    {
        $previous = $this->barangay;
        $this->barangay = $barangay;

        try {
            return $callback();
        } finally {
            $this->barangay = $previous;
        }
    }
}
