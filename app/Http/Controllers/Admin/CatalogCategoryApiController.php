<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogCategoryApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()
            ->withCount('menuItems')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('q')) {
            $needle = '%'.$request->string('q')->trim().'%';
            $query->where('name', 'like', $needle);
        }

        return response()->json([
            'categories' => $query->get()->map(fn (Category $c) => $this->serialize($c))->values(),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        /** @var array{name: string, sort_order?: int, is_active?: bool, is_kitchen?: bool, icon?: string|null} $data */
        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? 0;
        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }
        if (! array_key_exists('is_kitchen', $data)) {
            $data['is_kitchen'] = false;
        }

        $category = Category::query()->create($data);

        return response()->json([
            'message' => __('Category created.'),
            'category' => $this->serialize($category->loadCount('menuItems')),
        ], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();
        if (! array_key_exists('is_kitchen', $data)) {
            $data['is_kitchen'] = false;
        }

        $category->update($data);

        return response()->json([
            'message' => __('Category updated.'),
            'category' => $this->serialize($category->fresh()->loadCount('menuItems')),
        ]);
    }

    public function toggleActive(Category $category): JsonResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return response()->json([
            'message' => $category->is_active ? __('Category activated.') : __('Category deactivated.'),
            'category' => $this->serialize($category->fresh()->loadCount('menuItems')),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->menuItems()->exists()) {
            return response()->json([
                'message' => __('Move or delete menu items in this category before deleting it.'),
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => __('Category deleted.')]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'is_kitchen' => $category->is_kitchen,
            'icon' => $category->icon,
            'menu_items_count' => (int) ($category->menu_items_count ?? $category->menuItems()->count()),
        ];
    }
}
