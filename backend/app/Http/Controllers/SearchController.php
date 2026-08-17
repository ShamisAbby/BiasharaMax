<?php

namespace App\Http\Controllers;

use App\Domain\Search\Services\GlobalSearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Powers the ⌘K palette.
     *
     * This used to search Products and nothing else, which made the palette
     * close to useless: the things people actually hunt for by keyboard are
     * a customer they are on the phone to, an invoice number a supplier just
     * read out, or a screen they can't remember the path to.
     *
     * Every source is permission-gated inside the service — see
     * GlobalSearchService for why that matters more here than anywhere else
     * in the app.
     */
    public function __invoke(Request $request, GlobalSearchService $search)
    {
        return [
            'groups' => $search->search(
                $request->user(),
                $request->string('q')->trim()->value(),
            ),
        ];
    }
}
