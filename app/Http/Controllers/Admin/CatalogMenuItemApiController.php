<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogMenuItemBulkRequest;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogMenuItemApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MenuItem::query()->with(['category:id,name,is_active']);

        if ($request->filled('q')) {
            $needle = '%'.$request->string('q')->trim().'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('name', 'like', $needle)
                    ->orWhere('description', 'like', $needle);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $available = $request->query('available');
        if ($available === '1') {
            $query->where('is_available', true);
        } elseif ($available === '0') {
            $query->where('is_available', false);
        }

        $diet = $request->query('diet');
        if ($diet === 'veg') {
            $query->where('dietary_type', 'veg');
        } elseif ($diet === 'non_veg') {
            $query->where('dietary_type', 'non_veg');
        }

        $items = $query
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'items' => $items->map(fn (MenuItem $m) => $this->serialize($m))->values(),
        ]);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('menu', 'public');
        } else {
            unset($data['image']);
        }

        $item = MenuItem::query()->create($data);

        return response()->json([
            'message' => __('Menu item saved.'),
            'item' => $this->serialize($item->fresh(['category'])),
        ], 201);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): JsonResponse
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

        return response()->json([
            'message' => __('Menu item updated.'),
            'item' => $this->serialize($menuItem->fresh(['category'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function inlineUpdate(Request $request, MenuItem $menuItem): JsonResponse
    {
        $validated = $request->validate([
            'price' => ['sometimes', 'numeric', 'min:0'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'is_available' => ['sometimes', 'boolean'],
            'dietary_type' => ['sometimes', 'nullable', 'in:veg,non_veg'],
        ]);

        if ($request->filled('price') && isset($validated['price'])) {
            $dp = $menuItem->discount_price !== null ? (float) $menuItem->discount_price : null;
            if ($dp !== null && $dp > (float) $validated['price']) {
                return response()->json([
                    'message' => __('Discount cannot exceed regular price.'),
                    'errors' => ['price' => [__('Discount price must be ≤ regular price.')]],
                ], 422);
            }
        }

        $menuItem->update($validated);

        return response()->json([
            'message' => __('Updated.'),
            'item' => $this->serialize($menuItem->fresh(['category'])),
        ]);
    }

    public function duplicate(MenuItem $menuItem): JsonResponse
    {
        $copy = $menuItem->replicate();
        $copy->name = $menuItem->name.' (copy)';
        $copy->is_bestseller = false;
        $copy->is_popular = false;

        if ($menuItem->image) {
            $disk = Storage::disk('public');
            if ($disk->exists($menuItem->image)) {
                $ext = pathinfo($menuItem->image, PATHINFO_EXTENSION);
                $dir = dirname($menuItem->image);
                $newPath = $dir.'/'.Str::uuid()->toString().($ext ? '.'.$ext : '');
                $disk->copy($menuItem->image, $newPath);
                $copy->image = $newPath;
            }
        }

        $copy->save();

        return response()->json([
            'message' => __('Duplicate created.'),
            'item' => $this->serialize($copy->fresh(['category'])),
        ], 201);
    }

    public function bulk(CatalogMenuItemBulkRequest $request): JsonResponse
    {
        $action = $request->string('action')->toString();
        $ids = array_values(array_unique(array_map('intval', $request->input('ids', []))));

        $affected = 0;

        DB::transaction(function () use ($action, $request, $ids, &$affected): void {
            if ($action === 'activate') {
                $affected = MenuItem::query()->whereIn('id', $ids)->update(['is_available' => true]);

                return;
            }

            if ($action === 'deactivate') {
                $affected = MenuItem::query()->whereIn('id', $ids)->update(['is_available' => false]);

                return;
            }

            if ($action === 'set_category') {
                $cid = (int) $request->validated('category_id');
                $affected = MenuItem::query()->whereIn('id', $ids)->update(['category_id' => $cid]);

                return;
            }

            $items = MenuItem::query()->whereIn('id', $ids)->get();
            foreach ($items as $item) {
                if ($item->image) {
                    Storage::disk('public')->delete($item->image);
                }
                $item->delete();
                $affected++;
            }
        });

        return response()->json([
            'message' => __('Bulk action applied.'),
            'affected' => $affected,
        ]);
    }

    public function destroy(MenuItem $menuItem): JsonResponse
    {
        if ($menuItem->image) {
            Storage::disk('public')->delete($menuItem->image);
        }
        $menuItem->delete();

        return response()->json(['message' => __('Menu item deleted.')]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(MenuItem $m): array
    {
        $m->loadMissing('category');

        return [
            'id' => $m->id,
            'name' => $m->name,
            'description' => $m->description,
            'price' => (string) $m->price,
            'discount_price' => $m->discount_price !== null ? (string) $m->discount_price : null,
            'prep_minutes' => $m->prep_minutes,
            'is_bestseller' => $m->is_bestseller,
            'is_popular' => $m->is_popular,
            'is_available' => $m->is_available,
            'dietary_type' => $m->dietary_type,
            'image' => $m->image,
            'image_url' => $m->image_url,
            'category_id' => $m->category_id,
            'category' => $m->category ? [
                'id' => $m->category->id,
                'name' => $m->category->name,
            ] : null,
        ];
    }
}
