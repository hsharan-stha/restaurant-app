<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFloorTableRequest;
use App\Http\Requests\SyncFloorPlanRequest;
use App\Http\Requests\UpdateFloorTableDrawerRequest;
use App\Models\DiningTable;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DiningTableFloorPlanController extends Controller
{
    public function data(): JsonResponse
    {
        $tables = DiningTable::query()
            ->orderBy('table_number')
            ->get([
                'id',
                'floor_id',
                'table_number',
                'table_name',
                'shape',
                'x_position',
                'y_position',
                'width',
                'height',
                'scale_x',
                'scale_y',
                'rotation',
                'fill_color',
                'seat_capacity',
                'status',
            ]);

        return response()->json([
            'tables' => $tables,
        ]);
    }

    public function store(StoreFloorTableRequest $request): JsonResponse
    {
        $table = DiningTable::query()->create($request->validated());

        return response()->json([
            'table' => $table->fresh(),
        ], 201);
    }

    public function sync(SyncFloorPlanRequest $request): JsonResponse
    {
        $rows = $request->validated()['tables'];

        if (count($rows) === 0) {
            return response()->json(['ok' => true]);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                unset($row['id']);

                DiningTable::query()->whereKey($id)->update($row);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function show(DiningTable $diningTable): JsonResponse
    {
        $orderingUrl = route('table.order', $diningTable);

        return response()->json([
            'table' => $this->serializeTableForDrawer($diningTable),
            'ordering_url' => $orderingUrl,
            'guest_entry_url' => $diningTable->customer_entry_url,
            'qr_svg' => $this->qrCodeSvg($orderingUrl),
        ]);
    }

    public function update(UpdateFloorTableDrawerRequest $request, DiningTable $diningTable): JsonResponse
    {
        $data = $request->validated();
        if ($data !== []) {
            $diningTable->update($data);
        }

        $diningTable->refresh();

        $orderingUrl = route('table.order', $diningTable);

        return response()->json([
            'table' => $this->serializeTableForDrawer($diningTable),
            'ordering_url' => $orderingUrl,
            'guest_entry_url' => $diningTable->customer_entry_url,
            'qr_svg' => $this->qrCodeSvg($orderingUrl),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTableForDrawer(DiningTable $diningTable): array
    {
        return [
            'id' => $diningTable->id,
            'floor_id' => $diningTable->floor_id,
            'table_number' => $diningTable->table_number,
            'table_name' => $diningTable->table_name,
            'shape' => $diningTable->shape,
            'x_position' => $diningTable->x_position,
            'y_position' => $diningTable->y_position,
            'width' => $diningTable->width,
            'height' => $diningTable->height,
            'scale_x' => $diningTable->scale_x,
            'scale_y' => $diningTable->scale_y,
            'rotation' => $diningTable->rotation,
            'fill_color' => $diningTable->fill_color,
            'seat_capacity' => $diningTable->seat_capacity,
            'status' => $diningTable->status->value,
            'updated_at' => $diningTable->updated_at?->toIso8601String(),
        ];
    }

    protected function qrCodeSvg(string $url): string
    {
        $options = new QROptions([
            'svgAddXmlHeader' => false,
            'outputBase64' => false,
            'scale' => 8,
        ]);

        return (new QRCode($options))->render($url);
    }
}
