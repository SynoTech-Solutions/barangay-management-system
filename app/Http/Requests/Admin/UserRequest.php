<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    private const int USERNAME_MAX = 64;

    private const string USERNAME_PATTERN = '/^[A-Za-z0-9._-]+$/';

    private const int TEXT_MAX = 255;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => strtolower(trim($this->input('username')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $ignoreId = $user instanceof User ? $user->id : null;

        return [
            'name' => ['required', 'string', 'max:'.self::TEXT_MAX],
            'username' => [
                'required', 'string', 'max:'.self::USERNAME_MAX, 'regex:'.self::USERNAME_PATTERN,
                Rule::unique('users', 'username')->ignore($ignoreId),
            ],
            'designation' => ['nullable', 'string', 'max:'.self::TEXT_MAX],
            'email' => ['nullable', 'email', 'max:'.self::TEXT_MAX],
            'password' => $ignoreId === null ? ['required', 'confirmed', Password::defaults()] : ['prohibited'],
            'role_ids' => $ignoreId === null ? ['array'] : ['prohibited'],
            'role_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array{name: string, username: string, designation: string|null, email: string|null}
     */
    public function profile(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'username' => $this->string('username')->trim()->lower()->toString(),
            'designation' => $this->filled('designation') ? $this->string('designation')->trim()->toString() : null,
            'email' => $this->filled('email') ? $this->string('email')->trim()->lower()->toString() : null,
        ];
    }

    /**
     * @return list<int>
     */
    public function roleIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('role_ids', [])));
    }
}
