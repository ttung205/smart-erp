<?php

namespace App\Http\Controllers;

use App\Models\SaleReturn;
use App\Models\SaleReturnRequest;
use App\Support\DocumentNumberGenerator;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SaleReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SaleReturn::with(['request.documents', 'items'])->latest('id');
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn ($builder) => $builder->where('return_number', 'like', $search)
                ->orWhere('request_number', 'like', $search)->orWhere('customer_name', 'like', $search));
        }
        $paginator = $query->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json(['success' => true, 'data' => $paginator->items(), 'meta' => [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
        ]]);
    }

    public function show(SaleReturn $saleReturn): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $saleReturn->load(['request.documents', 'request.attachments', 'items'])]);
    }

    public function post(Request $request, SaleReturnRequest $saleReturnRequest): JsonResponse
    {
        abort_unless($saleReturnRequest->status === SaleReturnRequest::PENDING_POSTING, 409, 'Yêu cầu chưa được duyệt để ghi sổ.');
        if ($saleReturnRequest->saleReturn) {
            return response()->json(['success' => true, 'data' => $saleReturnRequest->saleReturn->load('items'), 'message' => 'Phiếu trả hàng đã được lập trước đó.']);
        }

        $acceptedItems = $saleReturnRequest->items()->where('accepted_quantity', '>', 0)->get();
        abort_if($acceptedItems->isEmpty(), 409, 'Yêu cầu không có hàng đạt chất lượng để ghi sổ.');
        $reference = "{$saleReturnRequest->request_number}-MAIN";
        $response = Http::withToken((string) $request->bearerToken())
            ->timeout(config('services.inventory.timeout', 3))
            ->post(config('services.inventory.url').'/api/v1/inventory/sales-returns/transfer', [
                'reference_doc' => $reference,
                'source_warehouse_id' => $saleReturnRequest->technical_warehouse_id,
                'destination_warehouse_id' => $saleReturnRequest->sales_warehouse_id,
                'items' => $acceptedItems->groupBy('product_id')->map(fn ($items, $productId): array => [
                    'product_id' => (int) $productId,
                    'quantity' => (int) $items->sum('accepted_quantity'),
                ])->values()->all(),
            ]);
        $this->ensureInventorySuccess($response);
        foreach ($acceptedItems as $item) {
            $item->update(['main_transfer_reference' => $reference]);
        }

        $actor = $request->attributes->get('actor');
        $saleReturn = DB::transaction(function () use ($saleReturnRequest, $actor): SaleReturn {
            $locked = SaleReturnRequest::lockForUpdate()->findOrFail($saleReturnRequest->id);
            $existing = SaleReturn::where('sale_return_request_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }
            $return = SaleReturn::create([
                'return_number' => DocumentNumberGenerator::next('sale-return-'.now()->format('Y'), 'SR-'.now()->format('Y').'-', 6),
                'sale_return_request_id' => $locked->id, 'request_number' => $locked->request_number,
                'customer_id' => $locked->customer_id, 'customer_code' => $locked->customer_code, 'customer_name' => $locked->customer_name,
                'return_date' => now()->toDateString(), 'technical_warehouse_id' => $locked->technical_warehouse_id,
                'technical_warehouse_name' => $locked->technical_warehouse_name, 'sales_warehouse_id' => $locked->sales_warehouse_id,
                'sales_warehouse_name' => $locked->sales_warehouse_name, 'total_amount' => 0,
                'created_by_id' => $actor->id, 'created_by_name' => $actor->name,
            ]);
            $total = 0;
            foreach ($locked->items()->where('accepted_quantity', '>', 0)->get() as $item) {
                $lineTotal = $item->accepted_quantity * $item->unit_price;
                $total += $lineTotal;
                $return->items()->create([
                    'sale_return_request_item_id' => $item->id, 'invoice_id' => $item->invoice_id,
                    'invoice_item_id' => $item->invoice_item_id, 'product_id' => $item->product_id,
                    'invoice_number' => $item->invoice_number, 'sku' => $item->sku, 'product_name' => $item->product_name,
                    'brand' => $item->brand, 'unit' => $item->unit, 'quantity' => $item->accepted_quantity,
                    'unit_price' => $item->unit_price, 'line_total' => $lineTotal,
                ]);
            }
            $return->update(['total_amount' => $total]);
            $locked->update(['status' => SaleReturnRequest::COMPLETED, 'posted_by_id' => $actor->id,
                'posted_by_name' => $actor->name, 'posted_at' => now()]);

            return $return;
        });

        return response()->json(['success' => true, 'data' => $saleReturn->load(['request.documents', 'items']), 'message' => 'Đã ghi sổ và lập phiếu trả hàng bán.']);
    }

    private function ensureInventorySuccess(Response $response): void
    {
        if (! $response->successful()) {
            abort(502, 'Inventory Service không thể điều chuyển tồn kho: '.($response->json('error.message') ?? $response->body()));
        }
    }
}
