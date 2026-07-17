<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Label;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** F11 — the admin owns the label vocabulary. */
class LabelController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        return view('admin.labels.index', [
            'labels' => Label::withCount('tickets')->orderBy('name')->get(),
            'colors' => Label::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $data = $this->validated($request);

        Label::create($data + ['created_by' => $request->user()->id]);

        return back()->with('status', "تم إضافة لابل «{$data['name']}».");
    }

    public function update(Request $request, Label $label): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $label->update($this->validated($request, $label));

        return back()->with('status', 'تم حفظ اللابل.');
    }

    public function destroy(Request $request, Label $label): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);

        $name = $label->name;
        $label->delete();

        return back()->with('status', "تم حذف لابل «{$name}».");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Label $label = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('labels', 'name')->ignore($label?->id)],
            // Only the semantic palette — no free colour picker (§ 6).
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
        ], [], ['name' => 'اسم اللابل', 'color' => 'اللون']);
    }
}
