<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTicketTypeRequest;
use App\Http\Requests\Admin\UpdateTicketTypeRequest;
use App\Models\Label;
use App\Models\TicketTypeDefinition;
use App\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ★ (2026-07-23) The admin owns the ticket-type list, same pattern as ticket
 * statuses (TicketStatusController) and priorities (PriorityController). Each
 * type's name, colour, icon and — the point of it — whether it needs approval
 * before assignment (F15) all edit from one screen.
 */
class TicketTypeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        return view('admin.ticket-types.index', [
            'types' => TicketTypeDefinition::orderBy('position')->get(),
            'colors' => Label::COLORS,
            'icons' => TicketTypeDefinition::ICONS,
        ]);
    }

    public function store(StoreTicketTypeRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validated();

        $type = TicketTypeDefinition::create($data + [
            'is_system' => false,
            'position' => (int) TicketTypeDefinition::max('position') + 1,
        ]);

        $logger->log(
            action: 'ticket_type.created',
            userId: $request->user()->id,
            subject: $type,
            changes: ['to' => $type->only('key', 'name_ar', 'needs_approval', 'default_points')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', "تم إضافة نوع «{$data['name_ar']}».");
    }

    public function update(UpdateTicketTypeRequest $request, TicketTypeDefinition $ticketType, ActivityLogger $logger): RedirectResponse
    {
        // default_points is in here because it feeds real bonus money — a change
        // to it has to be traceable to whoever made it (§ 5).
        $before = $ticketType->only('name_ar', 'color', 'icon', 'needs_approval', 'default_points');

        $ticketType->update($request->validated());

        $logger->log(
            action: 'ticket_type.updated',
            userId: $request->user()->id,
            subject: $ticketType,
            changes: ['from' => $before, 'to' => $ticketType->only('name_ar', 'color', 'icon', 'needs_approval', 'default_points')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'تم حفظ النوع.');
    }

    public function destroy(Request $request, TicketTypeDefinition $ticketType, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        if ($ticketType->is_system) {
            return back()->withErrors(['type' => 'الأنواع الأساسية للنظام مينفعش تتحذف.']);
        }

        $name = $ticketType->name_ar;

        try {
            $ticketType->delete();
        } catch (QueryException $e) {
            // FK on tickets.type is RESTRICT on purpose — a ticket sitting on
            // this type right now must not lose it silently.
            return back()->withErrors(['type' => "فيه تذاكر لسه بنوع «{$name}» — غيّرها الأول."]);
        }

        $logger->log(
            action: 'ticket_type.deleted',
            userId: $request->user()->id,
            subject: $ticketType,
            changes: ['from' => $ticketType->only('key', 'name_ar')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', "تم حذف نوع «{$name}».");
    }
}
