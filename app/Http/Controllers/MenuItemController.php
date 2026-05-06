<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    public function index(): View
    {
        $menuItems = MenuItem::query()->with('category')->orderBy('name')->get();

        return view('admin.menu-items.index', compact('menuItems'));
    }

    public function create(): View
    {
        $categories = Category::query()->orderBy('name')->get();

        return view('admin.menu-items.create', compact('categories'));
    }

    public function store(StoreMenuItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('menu', 'public');
        } else {
            unset($data['image']);
        }

        MenuItem::query()->create($data);

        return redirect()->route('menu-items.index')->with('status', 'Menu item created.');
    }

    public function edit(MenuItem $menuItem): View
    {
        $categories = Category::query()->orderBy('name')->get();

        return view('admin.menu-items.edit', compact('menuItem', 'categories'));
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            if ($menuItem->image) {
                Storage::disk('public')->delete($menuItem->image);
            }
            $data['image'] = $request->file('image')->store('menu', 'public');
        } else {
            unset($data['image']);
        }

        $menuItem->update($data);

        return redirect()->route('menu-items.index')->with('status', 'Menu item updated.');
    }

    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        if ($menuItem->image) {
            Storage::disk('public')->delete($menuItem->image);
        }
        $menuItem->delete();

        return redirect()->route('menu-items.index')->with('status', 'Menu item deleted.');
    }
}
