<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CalendarSettingController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LabelController;
use App\Http\Controllers\Admin\PointRuleController;
use App\Http\Controllers\Admin\PriorityController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TicketStatusController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\SubtaskScheduleController;
use App\Http\Controllers\SubtaskStatusController;
use App\Http\Controllers\TicketLinkController;
use App\Http\Controllers\TicketWatcherController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardMoveController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\TicketWorkflowController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;
use App\Http\Middleware\UseInstallConnection;
use Illuminate\Support\Facades\Route;

/*
 * The install wizard (F00.1). EnsureInstalled runs on every web request and
 * decides which side of the fence you are on: before installation everything
 * redirects here, and afterwards every route in this group answers 404.
 */
Route::prefix('install')->middleware(UseInstallConnection::class)->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'requirements'])->name('requirements');

    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database.store');

    Route::get('/migrate', [InstallController::class, 'migrate'])->name('migrate');
    Route::post('/migrate', [InstallController::class, 'runMigrate'])->name('migrate.run');

    Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('/admin', [InstallController::class, 'storeAdmin'])->name('admin.store');

    Route::get('/finish', [InstallController::class, 'finish'])->name('finish');
    Route::post('/finish', [InstallController::class, 'complete'])->name('finish.complete');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    // F00.2's 5-per-minute limit is enforced per email+ip inside the controller.
    // This outer cap is a coarse flood guard for one address spraying many
    // different emails, and is set high enough that a shared office ip is fine.
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:30,1');
});

/*
 * F24 — the client portal. Outside `auth` on purpose: the client never logs
 * in, the per-ticket password is the whole access control.
 */
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/{ticketNumber?}', [PortalController::class, 'show'])->name('show');
    // Keyed by ticket number + ip inside the controller, same shape as login's
    // throttle — this outer cap just stops one address spraying many tickets.
    Route::post('/verify', [PortalController::class, 'verify'])->middleware('throttle:20,1')->name('verify');
    Route::post('/{ticketNumber}/comment', [PortalController::class, 'comment'])
        ->middleware('throttle:30,1')
        ->name('comment');

    // A client reading a file off their own ticket. Separate from the staff
    // download routes on purpose — this one can only reach attachments that
    // hang off a public comment. F24
    Route::get('/{ticketNumber}/files/{attachment}', [PortalController::class, 'attachment'])
        ->name('attachment');
    Route::get('/{ticketNumber}/files/{attachment}/thumb', [PortalController::class, 'attachmentThumbnail'])
        ->name('attachment.thumb');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Stays reachable while must_change_password is set — ForcePasswordChange
    // lets exactly these through and redirects everything else here.
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.store');

    Route::get('/', [HomeController::class, 'index'])->name('home');

    // F03 / F04 / F05 — tickets. Authorisation is per object via TicketPolicy,
    // so there is no permission middleware here: a developer may open the
    // tickets they're assigned to without holding tickets.view.all.
    Route::resource('tickets', TicketController::class);
    Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])
        ->name('tickets.comments.store');

    // F06 / F07 / F15 / F16 — everything that moves a ticket.
    Route::prefix('tickets/{ticket}')->name('tickets.')->group(function () {
        Route::post('assign', [TicketWorkflowController::class, 'assign'])->name('assign');
        Route::post('status', [TicketWorkflowController::class, 'changeStatus'])->name('status.change');
        Route::post('work-logs/{workLog}/start', [TicketWorkflowController::class, 'start'])->name('work.start');
        Route::post('work-logs/{workLog}/finish', [TicketWorkflowController::class, 'finish'])->name('work.finish');
        Route::post('approve', [TicketWorkflowController::class, 'approve'])->name('approve');
        Route::post('reject', [TicketWorkflowController::class, 'reject'])->name('reject');
        Route::post('verify', [TicketWorkflowController::class, 'verify'])->name('verify');
        Route::post('reopen', [TicketWorkflowController::class, 'reopen'])->name('reopen');
        Route::post('notify-client', [TicketWorkflowController::class, 'notifyClient'])->name('notify');
        Route::post('close', [TicketWorkflowController::class, 'close'])->name('close');
        Route::post('portal/regenerate', [TicketWorkflowController::class, 'regeneratePortalPassword'])->name('portal.regenerate');
    });

    // F08 / F09 / F10 / F11 — the Jira layer, all scoped under their ticket.
    Route::prefix('tickets/{ticket}')->name('tickets.')->group(function () {
        Route::post('subtasks', [SubtaskController::class, 'store'])->name('subtasks.store');
        Route::put('subtasks/{subtask}', [SubtaskController::class, 'update'])->name('subtasks.update');
        Route::delete('subtasks/{subtask}', [SubtaskController::class, 'destroy'])->name('subtasks.destroy');
        Route::post('subtasks/reorder', [SubtaskController::class, 'reorder'])->name('subtasks.reorder');

        Route::post('time', [TimeEntryController::class, 'store'])->name('time.store');

        Route::post('links', [TicketLinkController::class, 'store'])->name('links.store');
        Route::delete('links/{link}', [TicketLinkController::class, 'destroy'])->name('links.destroy');

        Route::post('watch', [TicketWatcherController::class, 'toggle'])->name('watch');
        // F17
        Route::post('ratings', [RatingController::class, 'store'])->name('ratings.store');
        Route::put('labels', [TicketController::class, 'syncLabels'])->name('labels.sync');
    });

    Route::delete('time/{entry}', [TimeEntryController::class, 'destroy'])->name('time.destroy');

    // F13 — the calendar. Drag-to-reschedule is its own endpoint: it moves dates
    // only and answers json.
    Route::get('/calendar/me', [CalendarController::class, 'mine'])->name('calendar.mine');
    Route::get('/calendar/team', [CalendarController::class, 'team'])
        ->middleware('permission:reports.view')->name('calendar.team');
    Route::patch('/subtasks/{subtask}/schedule', [SubtaskScheduleController::class, 'move'])
        ->name('subtasks.schedule');

    // Same reasoning, one field over: status on its own, from a row on the
    // ticket page or a card on the board.
    Route::patch('/subtasks/{subtask}/status', [SubtaskStatusController::class, 'update'])
        ->name('subtasks.status');
    Route::get('/my-timesheet', [TimeEntryController::class, 'timesheet'])
        ->middleware('permission:time.log')->name('time.timesheet');

    // F12.1 — a drag between board columns. Its own endpoint, answering json:
    // the browser needs a message it can show while snapping the card back.
    Route::patch('tickets/{ticket}/board-column', [BoardMoveController::class, 'move'])
        ->name('board.move');

    Route::get('/my-board', [BoardController::class, 'mine'])
        ->middleware('permission:worklog.manage')->name('board.mine');
    Route::get('/board', [BoardController::class, 'team'])
        ->middleware('permission:tickets.view.all')->name('board.team');
    Route::get('/approvals', [QueueController::class, 'approvals'])->name('queues.approvals');
    Route::get('/testing-queue', [QueueController::class, 'testing'])->name('queues.testing');

    // F21 — one box for everything.
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // F19 — the numbers live here; you come to them on purpose.
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')->name('reports.index');
    Route::get('/reports/team-activity', [ReportController::class, 'teamActivity'])
        ->middleware('permission:reports.view')->name('reports.team-activity');
    Route::get('/employees/{user}', [ReportController::class, 'employee'])
        ->middleware('permission:reports.view')->name('reports.employee');
    Route::get('/points-report/detail', [ReportController::class, 'pointsDetail'])
        ->middleware('permission:points.view.all')
        ->name('reports.points-detail');
    Route::get('/points-report', [ReportController::class, 'points'])
        ->middleware('permission:points.view.all')
        ->name('reports.points');
    Route::get('/leaderboard', [ReportController::class, 'leaderboard'])
        ->middleware('permission:points.view.all')->name('reports.leaderboard');
    Route::get('/my-points', [ReportController::class, 'myPoints'])
        ->middleware('permission:points.view.own')->name('reports.my-points');

    // F19.3 — exports. Scoped to the requester's visibility.
    Route::get('/export/tickets', [ExportController::class, 'tickets'])->name('export.tickets');
    Route::get('/export/points', [ExportController::class, 'points'])->name('export.points');

    // F20
    // Type-ahead for every database-backed dropdown. Throttled: it is a search
    // endpoint reachable from every screen.
    Route::get('lookup/{resource}', LookupController::class)
        ->middleware('throttle:120,1')
        ->name('lookup');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    // The bell polls this. It is the seam a websocket would replace later.
    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');

    // F20.2 — a browser registering itself for push. Not under a permission
    // gate: allowing notifications on your own machine is not an admin action.
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::get('/notifications/{id}', [NotificationController::class, 'read'])->name('notifications.read');

    // The only path to a stored file. Each one authorises against its ticket.
    Route::prefix('attachments/{attachment}')->name('attachments.')->group(function () {
        Route::get('download', [AttachmentController::class, 'download'])->name('download');
        Route::get('thumb', [AttachmentController::class, 'thumbnail'])->name('thumb');
        Route::get('view', [AttachmentController::class, 'view'])->name('view');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        // F18 — the points matrix. points.rules.manage is admin-only.
        Route::middleware('permission:points.rules.manage')->group(function () {
            Route::get('point-rules', [PointRuleController::class, 'index'])->name('point-rules.index');
            Route::put('point-rules', [PointRuleController::class, 'update'])->name('point-rules.update');
            Route::put('point-rules/role', [PointRuleController::class, 'updateRole'])->name('point-rules.update-role');
            Route::post('point-rules/corrections', [PointRuleController::class, 'storeCorrection'])
                ->name('point-rules.corrections.store');
        });

        // F23 — read-only by design.
        Route::get('audit', [AuditController::class, 'index'])
            ->middleware('permission:audit.view')->name('audit.index');

        // Full-system backup/restore — its own permission (system.backup), not
        // settings.manage: restore replaces every row and every uploaded file
        // the system currently holds.
        Route::middleware('permission:system.backup')->group(function () {
            Route::get('backup', [BackupController::class, 'index'])->name('backup');
            Route::get('backup/download', [BackupController::class, 'download'])->name('backup.download');
            Route::post('backup/restore', [BackupController::class, 'restore'])->name('backup.restore');
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/settings', [SettingController::class, 'edit'])->name('settings');
            Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

            // F06.1 — the status list and the transition graph between them.
            Route::get('ticket-statuses', [TicketStatusController::class, 'index'])->name('ticket-statuses.index');
            Route::post('ticket-statuses', [TicketStatusController::class, 'store'])->name('ticket-statuses.store');
            Route::put('ticket-statuses/transitions', [TicketStatusController::class, 'updateTransitions'])->name('ticket-statuses.transitions');
            Route::put('ticket-statuses/{ticketStatus}', [TicketStatusController::class, 'update'])->name('ticket-statuses.update');
            Route::delete('ticket-statuses/{ticketStatus}', [TicketStatusController::class, 'destroy'])->name('ticket-statuses.destroy');

            // F22.1 — the priority list, SLA hours included on the same row.
            Route::get('priorities', [PriorityController::class, 'index'])->name('priorities.index');
            Route::post('priorities', [PriorityController::class, 'store'])->name('priorities.store');
            Route::put('priorities/{priority}', [PriorityController::class, 'update'])->name('priorities.update');
            Route::delete('priorities/{priority}', [PriorityController::class, 'destroy'])->name('priorities.destroy');
        });

        Route::middleware('permission:users.manage')->group(function () {
            Route::resource('roles', RoleController::class)->except('show');
            Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate');
        });

        // F01 — customers and their contacts.
        Route::middleware('permission:companies.manage')->group(function () {
            Route::resource('companies', CompanyController::class);

            // A global "find this person" screen across every company —
            // separate from the per-company list nested below.
            Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');

            Route::prefix('companies/{company}')->name('companies.contacts.')->group(function () {
                Route::get('contacts/create', [ContactController::class, 'create'])->name('create');
                Route::post('contacts', [ContactController::class, 'store'])->name('store');
                Route::get('contacts/{contact}/edit', [ContactController::class, 'edit'])->name('edit');
                Route::put('contacts/{contact}', [ContactController::class, 'update'])->name('update');
                Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('destroy');
            });
        });

        // F22.3 — users.
        Route::middleware('permission:users.manage')->group(function () {
            Route::resource('users', UserController::class)->except('show');
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
                ->name('users.reset-password');

            // F11 — the label vocabulary.
            Route::get('labels', [LabelController::class, 'index'])->name('labels.index');
            Route::post('labels', [LabelController::class, 'store'])->name('labels.store');
            Route::put('labels/{label}', [LabelController::class, 'update'])->name('labels.update');
            Route::delete('labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');

            // F14 — holidays and leave.
            // (point rules sit under their own permission, below)

            Route::prefix('calendar')->name('calendar.')->group(function () {
                Route::get('/', [CalendarSettingController::class, 'index'])->name('index');
                Route::post('holidays', [CalendarSettingController::class, 'storeHoliday'])->name('holidays.store');
                Route::delete('holidays/{holiday}', [CalendarSettingController::class, 'destroyHoliday'])->name('holidays.destroy');
                Route::post('leaves', [CalendarSettingController::class, 'storeLeave'])->name('leaves.store');
                Route::delete('leaves/{leave}', [CalendarSettingController::class, 'destroyLeave'])->name('leaves.destroy');
            });
        });

        /*
         * F02 — Excel import. The group has no permission middleware because
         * each type carries its own: companies/contacts need companies.manage,
         * users needs users.manage. ImportController checks per request.
         */
        Route::prefix('import')->name('import.')->group(function () {
            Route::get('/', [ImportController::class, 'index'])->name('index');
            Route::get('template/{type}', [ImportController::class, 'template'])->name('template');
            Route::post('upload', [ImportController::class, 'upload'])->name('upload');
            Route::get('{batch}/preview', [ImportController::class, 'preview'])->name('preview');
            Route::post('{batch}/confirm', [ImportController::class, 'confirm'])->name('confirm');
            Route::get('{batch}/report', [ImportController::class, 'report'])->name('report');
            Route::get('{batch}/errors', [ImportController::class, 'errors'])->name('errors');
        });
    });
});
