<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreditReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('callbacks', [LeadController::class, 'callbacksIndex'])->name('callbacks.index');

    Route::middleware('admin')->group(function (): void {
        Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('credit-reports', [CreditReportController::class, 'index'])->name('credit-reports.index');
        Route::get('credit-reports/pending-count', [CreditReportController::class, 'pendingCount'])->name('credit-reports.pending-count');
        Route::post('credit-reports/{creditReport}/result', [CreditReportController::class, 'uploadResult'])->name('credit-reports.result');
        Route::delete('credit-reports/{creditReport}', [CreditReportController::class, 'destroy'])->name('credit-reports.destroy');
        Route::get('leads/new', [LeadController::class, 'adminNewLeads'])->name('leads.new.index');
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('leads/import/sample', [LeadController::class, 'downloadSampleCsv'])->name('leads.import.sample');
        Route::get('leads/import/history/{importHistory}/{type}', [LeadController::class, 'downloadImportHistoryFile'])->name('leads.import.history.download');
        Route::get('leads/import', [LeadController::class, 'importForm'])->name('leads.import.form');
        Route::post('leads/import', [LeadController::class, 'import'])->name('leads.import');
        Route::get('leads/export', [LeadController::class, 'export'])->name('leads.export');
        Route::get('leads/export/txt', [LeadController::class, 'exportTxt'])->name('leads.export.txt');
        Route::get('leads/{lead}/download/txt', [LeadController::class, 'downloadLeadTxt'])->name('leads.download.txt');
        Route::resource('users', UserController::class)->except(['show']);
    });

    Route::middleware('agent')->group(function (): void {
        Route::get('agent/queue', [LeadController::class, 'agentQueue'])->name('agent.queue');
        Route::post('agent/queue/skip', [LeadController::class, 'agentSkip'])->name('agent.queue.skip');
        Route::post('agent/queue/take', [LeadController::class, 'agentTake'])->name('agent.queue.take');
        Route::get('agent/history', [LeadController::class, 'agentHistory'])->name('agent.history');
    });

    Route::post('leads/{lead}/notes', [LeadController::class, 'storeNote'])->name('leads.notes.store');
    Route::get('leads/{lead}/related/create', [LeadController::class, 'createRelated'])->name('leads.related.create');
    Route::post('leads/{lead}/related', [LeadController::class, 'storeRelated'])->name('leads.related.store');
    Route::post('leads/{lead}/credit-report/request', [CreditReportController::class, 'request'])->name('leads.credit-report.request');
    Route::post('leads/{lead}/credit-report/recheck', [CreditReportController::class, 'recheck'])->name('leads.credit-report.recheck');
    Route::get('credit-reports/{creditReport}/download', [CreditReportController::class, 'download'])->name('credit-reports.download');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
        Route::get('notifications/{crmNotification}/open', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('notifications/{crmNotification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('leads/{lead}/cards/create', [LeadController::class, 'createCard'])->name('leads.cards.create');
    Route::post('leads/{lead}/cards', [LeadController::class, 'storeCard'])->name('leads.cards.store');
    Route::get('leads/{lead}/cards/{card}/edit', [LeadController::class, 'editCard'])->name('leads.cards.edit');
    Route::put('leads/{lead}/cards/{card}', [LeadController::class, 'updateCard'])->name('leads.cards.update');
    Route::delete('leads/{lead}/cards/{card}', [LeadController::class, 'destroyCard'])->name('leads.cards.destroy');
    Route::resource('leads', LeadController::class);
});
