<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreditReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ReportController;
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

    Route::middleware('admin')->group(function (): void {
        Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('credit-reports', [CreditReportController::class, 'index'])->name('credit-reports.index');
        Route::get('credit-reports/pending-count', [CreditReportController::class, 'pendingCount'])->name('credit-reports.pending-count');
        Route::post('credit-reports/{creditReport}/result', [CreditReportController::class, 'uploadResult'])->name('credit-reports.result');
        Route::delete('credit-reports/{creditReport}', [CreditReportController::class, 'destroy'])->name('credit-reports.destroy');
        Route::get('leads/new', [LeadController::class, 'adminNewLeads'])->name('leads.new.index');
        Route::post('leads/new/history-limit', [LeadController::class, 'updateAgentHistoryLimit'])->name('leads.new.history-limit.update');
        Route::get('leads/import/sample', [LeadController::class, 'downloadSampleCsv'])->name('leads.import.sample');
        Route::get('leads/import/history/{importHistory}/{type}', [LeadController::class, 'downloadImportHistoryFile'])->name('leads.import.history.download');
        Route::get('leads/import', [LeadController::class, 'importForm'])->name('leads.import.form');
        Route::post('leads/import', [LeadController::class, 'import'])->name('leads.import');
        Route::get('leads/export', [LeadController::class, 'export'])->name('leads.export');
        Route::resource('users', UserController::class)->except(['show']);
    });

    Route::middleware('agent')->group(function (): void {
        Route::get('agent/queue', [LeadController::class, 'agentQueue'])->name('agent.queue');
        Route::post('agent/queue/skip', [LeadController::class, 'agentSkip'])->name('agent.queue.skip');
        Route::post('agent/queue/take', [LeadController::class, 'agentTake'])->name('agent.queue.take');
        Route::get('agent/history', [LeadController::class, 'agentHistory'])->name('agent.history');
    });

    Route::post('leads/{lead}/notes', [LeadController::class, 'storeNote'])->name('leads.notes.store');
    Route::post('leads/{lead}/credit-report/request', [CreditReportController::class, 'request'])->name('leads.credit-report.request');
    Route::post('leads/{lead}/credit-report/recheck', [CreditReportController::class, 'recheck'])->name('leads.credit-report.recheck');
    Route::get('credit-reports/{creditReport}/download', [CreditReportController::class, 'download'])->name('credit-reports.download');
    Route::get('leads/{lead}/cards/create', [LeadController::class, 'createCard'])->name('leads.cards.create');
    Route::post('leads/{lead}/cards', [LeadController::class, 'storeCard'])->name('leads.cards.store');
    Route::get('leads/{lead}/cards/{card}/edit', [LeadController::class, 'editCard'])->name('leads.cards.edit');
    Route::put('leads/{lead}/cards/{card}', [LeadController::class, 'updateCard'])->name('leads.cards.update');
    Route::delete('leads/{lead}/cards/{card}', [LeadController::class, 'destroyCard'])->name('leads.cards.destroy');
    Route::resource('leads', LeadController::class);
});
