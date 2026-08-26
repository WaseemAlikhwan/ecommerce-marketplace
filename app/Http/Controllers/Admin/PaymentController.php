<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderViewService;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly OrderViewService $orderViews,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::query()
            ->with(['vendorOrder.currency', 'currency'])
            ->latest('id')
            ->paginate(20);

        $rows = $this->orderViews->adminPaymentIndexRows(
            $payments->getCollection(),
            app()->getLocale(),
        );

        return view('admin.payments.index', [
            'payments' => $payments,
            'rows' => $rows,
        ]);
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);

        $detail = $this->orderViews->adminPaymentDetail($payment, app()->getLocale());

        return view('admin.payments.show', [
            'payment' => $detail,
        ]);
    }
}
