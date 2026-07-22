<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLinkTypeRequest;
use App\Http\Requests\Admin\UpdateLinkTypeRequest;
use App\Models\Label;
use App\Models\LinkTypeDefinition;
use App\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ★ (2026-07-24) The admin owns the ticket-link-type list, same pattern as
 * ticket types. Each type reads one way from the source ticket and another from
 * the target (F10), both editable here. `blocks` is a system row (it raises the
 * blocked marker) — renameable but not deletable.
 */
class LinkTypeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        return view('admin.link-types.index', [
            'types' => LinkTypeDefinition::orderBy('position')->get(),
            'colors' => Label::COLORS,
        ]);
    }

    public function store(StoreLinkTypeRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validated();

        $type = LinkTypeDefinition::create($data + [
            'is_system' => false,
            'position' => (int) LinkTypeDefinition::max('position') + 1,
        ]);

        $logger->log(
            action: 'link_type.created',
            userId: $request->user()->id,
            subject: $type,
            changes: ['to' => $type->only('key', 'name_ar', 'inverse_label_ar')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', "تم إضافة نوع ربط «{$data['name_ar']}».");
    }

    public function update(UpdateLinkTypeRequest $request, LinkTypeDefinition $linkType, ActivityLogger $logger): RedirectResponse
    {
        $before = $linkType->only('name_ar', 'inverse_label_ar', 'color');

        $linkType->update($request->validated());

        $logger->log(
            action: 'link_type.updated',
            userId: $request->user()->id,
            subject: $linkType,
            changes: ['from' => $before, 'to' => $linkType->only('name_ar', 'inverse_label_ar', 'color')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', 'تم حفظ نوع الربط.');
    }

    public function destroy(Request $request, LinkTypeDefinition $linkType, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        if ($linkType->is_system) {
            return back()->withErrors(['type' => 'أنواع الروابط الأساسية للنظام مينفعش تتحذف.']);
        }

        $name = $linkType->name_ar;

        try {
            $linkType->delete();
        } catch (QueryException) {
            // FK on ticket_links.type is RESTRICT — a link on this type right
            // now must not lose it silently.
            return back()->withErrors(['type' => "فيه روابط لسه بنوع «{$name}» — شيلها الأول."]);
        }

        $logger->log(
            action: 'link_type.deleted',
            userId: $request->user()->id,
            subject: $linkType,
            changes: ['from' => $linkType->only('key', 'name_ar')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', "تم حذف نوع ربط «{$name}».");
    }
}
