<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Invoices for Zercash purchases. An invoice is generated automatically on every checkout
 * (see CheckoutController) and shown in the user's profile.
 *
 *   GET /api/invoices        → the caller's invoices (paginated, newest first)
 *   GET /api/invoices/{id}   → one invoice (matches by _id, invoice_id, or order_id)
 */
class InvoiceApiController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 50);
        $paginator = Invoice::where('user_id', (string) Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(fn ($i) => $this->transform($i))->values();

        return ResponseHelper::sendResponse([
            'items'        => $items,
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
        ], 'Invoices fetched.');
    }

    public function show($id)
    {
        $uid = (string) Auth::id();
        $invoice = Invoice::where('user_id', $uid)
            ->where(function ($q) use ($id) {
                $q->where('_id', $id)
                  ->orWhere('invoice_id', $id)
                  ->orWhere('order_id', $id);
            })->first();

        if (!$invoice) {
            return ResponseHelper::sendResponse(null, 'Invoice not found.', false, 404);
        }
        return ResponseHelper::sendResponse($this->transform($invoice), 'Invoice fetched.');
    }

    /**
     * GET /api/invoices/{id}/download — server-rendered PDF of the invoice (binary download).
     * Matches the same id forms as show().
     */
    public function download($id)
    {
        $uid = (string) Auth::id();
        $invoice = Invoice::where('user_id', $uid)
            ->where(function ($q) use ($id) {
                $q->where('_id', $id)
                  ->orWhere('invoice_id', $id)
                  ->orWhere('order_id', $id);
            })->first();

        if (!$invoice) {
            return ResponseHelper::sendResponse(null, 'Invoice not found.', false, 404);
        }

        $data = $this->transform($invoice);
        $date = $data['date'] ? Carbon::parse($data['date'])->format('d M Y, h:i A') : '';

        $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $data, 'date' => $date])
            ->setPaper('a4');

        $fileName = ($data['invoice_id'] ?: 'invoice') . '.pdf';
        return $pdf->download($fileName);
    }

    /** Normalise an invoice into a clean, stable shape for the mobile app. */
    private function transform(Invoice $i): array
    {
        return [
            '_id'             => (string) $i->_id,
            'invoice_id'      => $i->invoice_id ?? '',
            'order_id'        => $i->order_id ?? '',
            'order_number'    => $i->order_number ?? '',
            'status'          => $i->status ?? 'COMPLETED',
            'payment_method'  => $i->payment_method ?? '',
            'transaction_type'=> $i->transaction_type ?? 'purchase',
            'date'            => $i->date ?? $i->created_at,
            'customer'        => [
                'name'  => trim(($i->first_name ?? '') . ' ' . ($i->last_name ?? '')),
                'email' => $i->email ?? '',
                'phone' => $i->phone ?? '',
            ],
            'items'           => is_array($i->items) ? $i->items : [],
            'subtotal'        => (float) ($i->subtotal ?? 0),
            'total_zer'       => (float) ($i->total_zer ?? 0),
            'total_fiat'      => (float) ($i->total_fiat ?? 0),
            'cashback_earned' => (float) ($i->cashback_earned ?? 0),
            'created_at'      => $i->created_at,
        ];
    }
}
