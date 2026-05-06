<?php

namespace App\Repositories\Contracts;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;

interface MenuItemRepositoryInterface
{
    public function find(int $id): ?MenuItem;

    public function allWithCategories(): Collection;
}
