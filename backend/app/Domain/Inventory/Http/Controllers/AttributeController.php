<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Http\Requests\AttributeStoreRequest;
use App\Domain\Inventory\Http\Requests\AttributeUpdateRequest;
use App\Domain\Inventory\Http\Resources\AttributeResource;
use App\Domain\Inventory\Models\Attribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Attribute::class);

        $attributes = Attribute::query()
            ->where('business_id', $request->user()->business_id)
            ->with('values')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Attributes', [
            'attributes' => AttributeResource::collection($attributes),
        ]);
    }

    public function store(AttributeStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $attribute = Attribute::create([
                'business_id' => $request->user()->business_id,
                'name' => $request->validated('name'),
                'slug' => Str::slug($request->validated('name')),
                'input_type' => $request->validated('input_type'),
                'is_variant_attribute' => $request->boolean('is_variant_attribute', true),
                'status' => $request->validated('status'),
            ]);

            foreach ($request->validated('values', []) as $index => $value) {
                $attribute->values()->create(['value' => $value, 'sort_order' => $index]);
            }
        });

        return back()->with('status', 'attribute-created');
    }

    public function update(AttributeUpdateRequest $request, Attribute $attribute): RedirectResponse
    {
        DB::transaction(function () use ($request, $attribute) {
            $attribute->update([
                'name' => $request->validated('name'),
                'input_type' => $request->validated('input_type'),
                'is_variant_attribute' => $request->boolean('is_variant_attribute', true),
                'status' => $request->validated('status'),
            ]);

            $values = $request->validated('values', []);
            $existingValues = $attribute->values()->pluck('value')->all();

            foreach ($values as $index => $value) {
                if (in_array($value, $existingValues, true)) {
                    continue;
                }

                $attribute->values()->create(['value' => $value, 'sort_order' => $index]);
            }

            $attribute->values()->whereNotIn('value', $values)->delete();
        });

        return back()->with('status', 'attribute-updated');
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        $this->authorize('delete', $attribute);

        $attribute->delete();

        return back()->with('status', 'attribute-deleted');
    }
}
