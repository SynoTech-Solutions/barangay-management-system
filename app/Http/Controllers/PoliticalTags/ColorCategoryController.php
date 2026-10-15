<?php

namespace App\Http\Controllers\PoliticalTags;

use App\Http\Controllers\Controller;
use App\Http\Requests\PoliticalTags\ColorCategoryRequest;
use App\Http\Support\Toast;
use App\Models\ColorCategory;
use App\Services\PoliticalTags\ColorCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ColorCategoryController extends Controller
{
    public function __construct(private readonly ColorCategoryService $categories) {}

    public function index(): Response
    {
        return Inertia::render('political-tags/Categories', [
            'categories' => $this->categories->all()->map(fn (ColorCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'color_hex' => $category->color_hex,
                'is_active' => $category->is_active,
                'tags_count' => $category->tags_count ?? 0,
            ])->values()->all(),
        ]);
    }

    public function store(ColorCategoryRequest $request): RedirectResponse
    {
        $this->categories->create($request->name(), $request->colorHex(), $this->actor($request));
        Toast::success('messages.color_category_saved');

        return back();
    }

    public function update(ColorCategoryRequest $request, ColorCategory $category): RedirectResponse
    {
        $this->categories->update($category, $request->name(), $request->colorHex(), $this->actor($request));
        Toast::success('messages.color_category_saved');

        return back();
    }

    public function deactivate(Request $request, ColorCategory $category): RedirectResponse
    {
        $this->categories->setActive($category, false, $this->actor($request));
        Toast::success('messages.color_category_deactivated');

        return back();
    }

    public function reactivate(Request $request, ColorCategory $category): RedirectResponse
    {
        $this->categories->setActive($category, true, $this->actor($request));
        Toast::success('messages.color_category_reactivated');

        return back();
    }
}
