<?php

namespace App\Http\Controllers\PoliticalTags;

use App\Http\Controllers\Controller;
use App\Http\Requests\PoliticalTags\BulkColorRequest;
use App\Http\Support\Toast;
use App\Services\PoliticalTags\PoliticalTagService;
use Illuminate\Http\RedirectResponse;

class ResidentBulkColorController extends Controller
{
    public function __invoke(BulkColorRequest $request, PoliticalTagService $tags): RedirectResponse
    {
        $changed = $tags->assign($request->residentIds(), $request->categoryId(), $this->actor($request));
        Toast::success('messages.residents_color_changed', ['count' => $changed]);

        return back();
    }
}
