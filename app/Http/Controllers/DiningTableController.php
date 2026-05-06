<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiningTableRequest;
use App\Http\Requests\UpdateDiningTableRequest;
use App\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DiningTableController extends Controller
{
    public function index(): View
    {
        $tables = DiningTable::query()->orderBy('table_number')->get();

        return view('admin.dining-tables.index', compact('tables'));
    }

    public function create(): View
    {
        return view('admin.dining-tables.create');
    }

    public function store(StoreDiningTableRequest $request): RedirectResponse
    {
        DiningTable::query()->create($request->validated());

        return redirect()->route('dining-tables.index')->with('status', 'Table created.');
    }

    public function edit(DiningTable $diningTable): View
    {
        return view('admin.dining-tables.edit', compact('diningTable'));
    }

    public function update(UpdateDiningTableRequest $request, DiningTable $diningTable): RedirectResponse
    {
        $diningTable->update($request->validated());

        return redirect()->route('dining-tables.index')->with('status', 'Table updated.');
    }

    public function destroy(DiningTable $diningTable): RedirectResponse
    {
        $diningTable->delete();

        return redirect()->route('dining-tables.index')->with('status', 'Table deleted.');
    }
}
