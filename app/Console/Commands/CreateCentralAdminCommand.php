<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Access\RoleSynchronizer;
use App\Services\Audit\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateCentralAdminCommand extends Command
{
    protected $signature = 'bms:central-admin:create';

    protected $description = 'Create a central administrator account (global staff with access to every barangay)';

    public function handle(RoleSynchronizer $roles, AuditTrail $audit): int
    {
        $name = text(label: 'Full name', required: true);
        $username = strtolower(text(label: 'Username', required: true));
        $secret = password(label: 'Password', required: true);

        $validator = Validator::make(
            ['username' => $username, 'password' => $secret],
            ['username' => ['unique:users,username'], 'password' => [Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($roles, $audit, $name, $username, $secret): void {
            $roles->sync();

            $user = new User;
            $user->fill(['name' => $name, 'username' => $username, 'password' => $secret]);
            $user->is_active = true;
            $user->save();

            UserRole::query()->create([
                'user_id' => $user->id,
                'role_id' => $roles->systemRole(SystemRole::CentralAdmin)->id,
                'barangay_id' => null,
            ]);

            $audit->record(AuditAction::Created, $user, after: $user->only(['name', 'username']), actorId: null);
        });

        $this->components->info("Central administrator {$username} created.");

        return self::SUCCESS;
    }
}
