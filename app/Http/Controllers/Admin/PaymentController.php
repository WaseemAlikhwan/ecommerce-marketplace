<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentCollectionException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly OrderViewService $orderViews,
        private readonly PaymentGateway $paymentGateway,
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

    public function show(Request $request, Payment $payment): View
    {
        $this->authorize('view', $payment);

        $detail = $this->orderViews->adminPaymentDetail($payment, app()->getLocale());

        return view('admin.payments.show', [
            'payment' => $detail,
            'canCollect' => $request->user()?->can('collect', $payment) ?? false,
        ]);
    }

    public function collect(Payment $payment): RedirectResponse
    {
        $this->authorize('collect', $payment);

        try {
            $this->paymentGateway->markCollected($payment);
        } catch (PaymentCollectionException $e) {
            if ($e->errorCode === PaymentCollectionException::UNAUTHORIZED) {
                abort(404);
            }

            return redirect()
                ->route('admin.payments.show', $payment)
                ->withErrors([
                    'collect' => __('This payment cannot be marked as collected.'),
                ]);
        }

        return redirect()
            ->route('admin.payments.show', $payment)
            ->with('status', __('Payment marked as collected.'));
    }
}
