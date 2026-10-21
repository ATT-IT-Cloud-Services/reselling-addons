<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Servers\Ignite\Lib\ServiceActions;

Route::group(['middleware' => ['web', 'auth']], function (): void {
    Route::post('/account/ignite/service/rdns', static function (Request $request) {
        return redirect()
            ->back()
            ->with('ignite_flash', ServiceActions::setRdns($request));
    })->name('ignite.service.rdns');

    Route::post('/account/ignite/service/dns', static function (Request $request) {
        return redirect()
            ->back()
            ->with('ignite_flash', ServiceActions::createDnsRecord($request));
    })->name('ignite.service.dns.create');

    Route::post('/account/ignite/service/dns/delete', static function (Request $request) {
        return redirect()
            ->back()
            ->with('ignite_flash', ServiceActions::deleteDnsRecord($request));
    })->name('ignite.service.dns.delete');
    Route::post('/account/ignite/service/backups/restore', static function (Request $request) {
        return redirect()
            ->back()
            ->with('ignite_flash', ServiceActions::restoreBackup($request));
    })->name('ignite.service.backups.restore');

    Route::post('/account/ignite/service/backups/delete', static function (Request $request) {
        return redirect()
            ->back()
            ->with('ignite_flash', ServiceActions::deleteBackup($request));
    })->name('ignite.service.backups.delete');
});
