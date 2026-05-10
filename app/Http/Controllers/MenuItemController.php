<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    public function index(): View
    {
        return view('admin.menu-items.manage');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('menu-items.index', ['new' => '1']);
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

    public function edit(MenuItem $menuItem): RedirectResponse
    {
        return redirect()->route('menu-items.index', ['edit' => $menuItem->id]);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['remove_image'])) {
            if ($menuItem->image) {
                Storage::disk('public')->delete($menuItem->image);
            }
            $menuItem->image = null;
        }
        unset($data['remove_image']);

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
