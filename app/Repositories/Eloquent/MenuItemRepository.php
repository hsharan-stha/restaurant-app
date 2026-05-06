<?php

namespace App\Repositories\Eloquent;

use App\Models\MenuItem;
use App\Repositories\Contracts\MenuItemRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MenuItemRepository implements MenuItemRepositoryInterface
{
    public function find(int $id): ?MenuItem
    {
        return MenuItem::query()->find($id);
    }

    public function allWithCategories(): Collection
    {
        return MenuItem::query()
            ->with('category')
            ->orderBy('name')
            ->get();
    }
}
