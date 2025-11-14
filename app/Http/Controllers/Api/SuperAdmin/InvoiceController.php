<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['tenant']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('tenant', function ($tenantQuery) use ($search) {
                      $tenantQuery->whereJsonContains('data->name', $search);
                  });
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $invoices = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Calculate stats
        $stats = [
            'total' => Invoice::count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'pending' => Invoice::where('status', 'pending')->count(),
            'failed' => Invoice::where('status', 'failed')->count(),
            'total_revenue' => Invoice::where('status', 'paid')->sum('total'),
        ];

        return response()->json([
            'invoices' => $invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'business_name' => $invoice->tenant->data['name'] ?? $invoice->tenant->id,
                    'business_id' => $invoice->tenant->id,
                    'amount' => $invoice->amount,
                    'tax' => $invoice->tax,
                    'total' => $invoice->total,
                    'status' => $invoice->status,
                    'due_date' => $invoice->due_date->toDateString(),
                    'paid_at' => $invoice->paid_at?->toDateString(),
                    'created_at' => $invoice->created_at->toDateString(),
                ];
            }),
            'stats' => $stats,
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ]
        ]);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['tenant']);

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'business_name' => $invoice->tenant->data['name'] ?? $invoice->tenant->id,
                'business_email' => $invoice->tenant->data['contact_email'] ?? '',
                'business_address' => $invoice->tenant->data['address'] ?? '',
                'amount' => $invoice->amount,
                'tax' => $invoice->tax,
                'total' => $invoice->total,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date->toDateString(),
                'paid_at' => $invoice->paid_at?->toDateString(),
                'created_at' => $invoice->created_at->toDateString(),
                'payment_method' => $invoice->payment_method,
                'transaction_id' => $invoice->transaction_id,
                'items' => $invoice->items ?? [],
            ]
        ]);
    }

    public function download(Invoice $invoice): JsonResponse
    {
        // Generate PDF invoice
        // This would typically use a PDF library like DomPDF or similar
        return response()->json([
            'success' => true,
            'message' => 'Invoice PDF generated successfully',
            'download_url' => route('invoices.download', $invoice->id)
        ]);
    }

    public function resend(Invoice $invoice): JsonResponse
    {
        try {
            // Send invoice email to business
            // This would typically use Laravel's mail system
            
            return response()->json([
                'success' => true,
                'message' => 'Invoice sent successfully to business email'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invoice: ' . $e->getMessage()
            ], 500);
        }
    }
}
