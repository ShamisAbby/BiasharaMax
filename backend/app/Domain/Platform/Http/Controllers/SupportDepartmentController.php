<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Support\Models\SupportDepartment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportDepartmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:support_departments,slug'],
            'description' => ['nullable', 'string'],
        ]);

        SupportDepartment::query()->create($validated);

        return back()->with('status', 'department-created');
    }

    public function update(Request $request, SupportDepartment $supportDepartment): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $supportDepartment->update($validated);

        return back()->with('status', 'department-updated');
    }
}
