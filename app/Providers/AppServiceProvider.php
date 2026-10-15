<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Enums\SmsDriver;
use App\Models\User;
use App\Services\Access\PermissionResolver;
use App\Services\Features\FeatureFlags;
use App\Services\Finance\FundConsumption;
use App\Services\Finance\VoucherFundConsumption;
use App\Services\Forms\Pdf\PdfFormFiller;
use App\Services\Forms\Pdf\QpdfFormFiller;
use App\Services\Forms\Pdf\UnconfiguredPdfFormFiller;
use App\Services\Holds\BlotterHoldChecker;
use App\Services\Holds\ResidentHoldChecker;
use App\Services\Sms\HttpSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\NullSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Support\Config\BmsSettings;
use App\Support\Config\SmsSettings;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BmsSettings::class, fn (): BmsSettings => BmsSettings::fromArray((array) config('bms')));
        $this->app->scoped(CurrentBarangay::class);
        $this->app->scoped(PermissionResolver::class);
        $this->app->scoped(FeatureFlags::class);
        $this->app->bind(ResidentHoldChecker::class, BlotterHoldChecker::class);
        $this->app->bind(FundConsumption::class, VoucherFundConsumption::class);
        $this->app->bind(PdfFormFiller::class, function (): PdfFormFiller {
            $forms = $this->app->make(BmsSettings::class)->forms;

            return $forms->qpdfBinary === null
                ? new UnconfiguredPdfFormFiller
                : new QpdfFormFiller($forms->qpdfBinary, $forms->pdfFillTimeoutSeconds);
        });
        $this->app->bind(SmsSettings::class, fn (): SmsSettings => $this->app->make(BmsSettings::class)->sms);
        $this->app->bind(SmsGateway::class, function (): SmsGateway {
            $sms = $this->app->make(SmsSettings::class);

            return match ($sms->driver) {
                SmsDriver::Log => new LogSmsGateway,
                SmsDriver::Http => new HttpSmsGateway($sms, $this->app->make(HttpFactory::class)),
                SmsDriver::Null => new NullSmsGateway,
            };
        });
    }

    public function boot(): void
    {
        $settings = $this->app->make(BmsSettings::class);

        $this->configureDefaults($settings);
        $this->configureAuthorization();
    }

    private function configureDefaults(BmsSettings $settings): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands($this->app->isProduction());

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(function () use ($settings): Password {
            $rule = Password::min($settings->auth->passwordMinLength)->letters()->numbers();

            return $settings->auth->passwordCheckBreached && $this->app->isProduction()
                ? $rule->uncompromised()
                : $rule;
        });
    }

    private function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            $permission = Permission::tryFrom($ability);

            return $permission === null ? null : $this->app->make(PermissionResolver::class)->has($user, $permission);
        });
    }
}
