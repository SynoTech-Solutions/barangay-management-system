<?php

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class OnboardBarangayRequest extends FormRequest
{
    private const int PSGC_LENGTH_MAX = 10;

    private const string PSGC_PATTERN = '/^[0-9]+$/';

    private const int TEXT_MAX = 255;

    private const int USERNAME_MAX = 64;

    private const string USERNAME_PATTERN = '/^[A-Za-z0-9._-]+$/';

    protected function prepareForValidation(): void
    {
        $username = $this->input('admin.username');
        if (is_string($username)) {
            $this->merge(['admin' => [...(array) $this->input('admin'), 'username' => strtolower(trim($username))]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'municipality_id' => ['required', 'integer', 'exists:municipalities,id'],
            'psgc_code' => ['required', 'string', 'max:'.self::PSGC_LENGTH_MAX, 'regex:'.self::PSGC_PATTERN, 'unique:barangays,psgc_code'],
            'name' => ['required', 'string', 'max:'.self::TEXT_MAX],
            'admin' => ['nullable', 'array'],
            'admin.name' => ['required_with:admin', 'string', 'max:'.self::TEXT_MAX],
            'admin.username' => ['required_with:admin', 'string', 'max:'.self::USERNAME_MAX, 'regex:'.self::USERNAME_PATTERN, 'unique:users,username'],
            'admin.email' => ['nullable', 'email', 'max:'.self::TEXT_MAX],
            'admin.password' => ['required_with:admin', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array{municipality_id: int, psgc_code: string, name: string}
     */
    public function barangay(): array
    {
        return [
            'municipality_id' => $this->integer('municipality_id'),
            'psgc_code' => $this->string('psgc_code')->trim()->toString(),
            'name' => $this->string('name')->trim()->toString(),
        ];
    }

    /**
     * @return array{name: string, username: string, email: string|null, password: string}|null
     */
    public function firstAdmin(): ?array
    {
        if (! $this->filled('admin.username')) {
            return null;
        }

        return [
            'name' => $this->string('admin.name')->trim()->toString(),
            'username' => $this->string('admin.username')->trim()->lower()->toString(),
            'email' => $this->filled('admin.email') ? $this->string('admin.email')->trim()->lower()->toString() : null,
            'password' => $this->string('admin.password')->toString(),
        ];
    }
}
