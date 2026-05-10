<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.manage');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('categories.index', ['new' => '1']);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if (! array_key_exists('sort_order', $data)) {
            $data['sort_order'] = 0;
        }
        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = $request->boolean('is_active', true);
        }

        Category::query()->create($data);

        return redirect()->route('categories.index')->with('status', 'Category created.');
    }

    public function edit(Category $category): RedirectResponse
    {
        return redirect()->route('categories.index', ['edit' => $category->id]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->menuItems()->exists()) {
            return redirect()->route('categories.index')
                ->withErrors(['catalog' => 'Move or delete menu items in this category first.']);
        }

        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Category deleted.');
    }
}
