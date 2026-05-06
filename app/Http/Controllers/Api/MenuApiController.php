<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class MenuApiController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->with(['menuItems' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }
}
