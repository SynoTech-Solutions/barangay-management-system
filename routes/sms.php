<?php

use App\Enums\FeatureFlag;
use App\Enums\Permission;
use App\Http\Controllers\Sms\ResidentSmsController;
use App\Http\Controllers\Sms\SmsComposeController;
use App\Http\Controllers\Sms\SmsGroupController;
use App\Http\Controllers\Sms\SmsGroupMemberController;
use App\Http\Controllers\Sms\SmsGroupMessageController;
use App\Http\Controllers\Sms\SmsMessageController;
use App\Http\Controllers\Sms\SmsTemplateController;
use Illuminate\Support\Facades\Route;

$can = fn (Permission $permission): string => 'can:'.$permission->value;
$feature = fn (FeatureFlag $flag): string => 'feature:'.$flag->value;

Route::middleware(['auth', 'barangay', $feature(FeatureFlag::Sms)])->prefix('sms')->name('sms.')->group(function () use ($can, $feature) {
    Route::middleware($can(Permission::SmsView))->whereNumber('message')->group(function () {
        Route::get('/', [SmsMessageController::class, 'index'])->name('index');
        Route::get('messages/{message}', [SmsMessageController::class, 'show'])->name('show');
    });

    Route::middleware($can(Permission::SmsSend))->group(function () {
        Route::get('create', [SmsComposeController::class, 'create'])->name('create');
        Route::post('/', [SmsComposeController::class, 'store'])->name('store');
        Route::post('retry', [SmsMessageController::class, 'retry'])->name('retry');
    });

    Route::post('cancel', [SmsMessageController::class, 'cancel'])->middleware($can(Permission::SmsCancel))->name('cancel');

    Route::middleware($feature(FeatureFlag::SmsGroups))->prefix('groups')->name('groups.')->whereNumber(['group', 'member'])->group(function () use ($can) {
        Route::middleware($can(Permission::SmsGroupsManage))->group(function () {
            Route::get('/', [SmsGroupController::class, 'index'])->name('index');
            Route::post('/', [SmsGroupController::class, 'store'])->name('store');
            Route::get('{group}', [SmsGroupController::class, 'show'])->name('show');
            Route::put('{group}', [SmsGroupController::class, 'update'])->name('update');
            Route::post('{group}/deactivate', [SmsGroupController::class, 'deactivate'])->name('deactivate');
            Route::post('{group}/reactivate', [SmsGroupController::class, 'reactivate'])->name('reactivate');
            Route::post('{group}/members', [SmsGroupMemberController::class, 'store'])->name('members.store');
            Route::delete('{group}/members/{member}', [SmsGroupMemberController::class, 'destroy'])->scopeBindings()->name('members.destroy');
        });

        Route::middleware($can(Permission::SmsSend))->group(function () {
            Route::get('{group}/message', [SmsGroupMessageController::class, 'create'])->name('message.create');
            Route::post('{group}/message', [SmsGroupMessageController::class, 'store'])->name('message.store');
        });
    });

    Route::middleware([$feature(FeatureFlag::SmsTemplates), $can(Permission::SmsTemplatesManage)])->prefix('templates')->name('templates.')->whereNumber('template')->group(function () {
        Route::get('/', [SmsTemplateController::class, 'index'])->name('index');
        Route::post('/', [SmsTemplateController::class, 'store'])->name('store');
        Route::put('{template}', [SmsTemplateController::class, 'update'])->name('update');
        Route::delete('{template}', [SmsTemplateController::class, 'destroy'])->name('destroy');
    });

    Route::middleware([$feature(FeatureFlag::SmsFromResidentLists), $can(Permission::ResidentsView)])->prefix('residents')->name('residents.')->group(function () use ($can, $feature) {
        Route::middleware($can(Permission::SmsSend))->group(function () {
            Route::get('send', [ResidentSmsController::class, 'createSend'])->name('send.create');
            Route::post('send', [ResidentSmsController::class, 'storeSend'])->name('send.store');
        });

        Route::middleware([$feature(FeatureFlag::SmsGroups), $can(Permission::SmsGroupsManage)])->group(function () {
            Route::get('group', [ResidentSmsController::class, 'createGroup'])->name('group.create');
            Route::post('group', [ResidentSmsController::class, 'storeGroup'])->name('group.store');
        });
    });
});
