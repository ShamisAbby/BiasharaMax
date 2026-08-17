<?php

namespace App\Domain\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Inventory\Http\Requests\TagStoreRequest;
use App\Domain\Inventory\Http\Resources\TagResource;
use App\Domain\Inventory\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tag::class);

        $tags = Tag::query()
            ->where('business_id', $request->user()->business_id)
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Tags', [
            'tags' => TagResource::collection($tags),
        ]);
    }

    public function store(TagStoreRequest $request): RedirectResponse
    {
        Tag::create([
            'business_id' => $request->user()->business_id,
            'name' => $request->validated('name'),
            'slug' => Str::slug($request->validated('name')),
        ]);

        return back()->with('status', 'tag-created');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return back()->with('status', 'tag-deleted');
    }
}
