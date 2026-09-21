<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\FulfillBookingOrder;
use App\Models\BookingOrder;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CartOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $propertyId = $request->query('property_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $baseQuery = BookingOrder::query()
            ->with([
                'unit.property:id,name,slug',
                'tenant:id,name,email,phone',
                'lease:id,reference,status,start_date,end_date',
                'invoice:id,reference,status,total',
            ]);

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('cart_token', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%")
                    ->orWhere('guest_phone', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('unit', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhereHas('property', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        if ($status && $status !== 'all') {
            $baseQuery->where('status', $status);
        }

        if ($propertyId && $propertyId !== 'all') {
            $baseQuery->whereHas('unit', fn ($uq) => $uq->where('property_id', $propertyId));
        }

        if ($startDate) {
            $baseQuery->whereDate('start_date', '>=', $startDate);
        }

        if ($endDate) {
            $baseQuery->whereDate('start_date', '<=', $endDate);
        }

        $orders = (clone $baseQuery)
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Statistics aggregate
        $allOrdersQuery = BookingOrder::query();
        $stats = [
            'total_orders' => (clone $allOrdersQuery)->count(),
            'total_amount' => (float) (clone $allOrdersQuery)->sum('amount'),
            'pending_orders' => (clone $allOrdersQuery)->where('status', BookingOrder::STATUS_PENDING)->count(),
            'pending_amount' => (float) (clone $allOrdersQuery)->where('status', BookingOrder::STATUS_PENDING)->sum('amount'),
            'paid_orders' => (clone $allOrdersQuery)->where('status', BookingOrder::STATUS_PAID)->count(),
            'paid_amount' => (float) (clone $allOrdersQuery)->where('status', BookingOrder::STATUS_PAID)->sum('amount'),
            'conflict_orders' => (clone $allOrdersQuery)->where('status', BookingOrder::STATUS_PAYMENT_CONFLICT)->count(),
            'expired_orders' => (clone $allOrdersQuery)->whereIn('status', [BookingOrder::STATUS_EXPIRED, BookingOrder::STATUS_CANCELLED])->count(),
        ];

        // Available properties and units for Create/Edit Modal dropdowns
        $properties = Property::query()
            ->where('is_active', true)
            ->with(['units' => function ($q) {
                $q->select('id', 'property_id', 'name', 'status', 'capacity')
                    ->with(['activeRates:id,unit_id,amount,billing_unit,billing_interval']);
            }])
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        return Inertia::render('cart-orders/index', [
            'orders' => $orders,
            'stats' => $stats,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? 'all',
                'property_id' => $propertyId ?? 'all',
                'start_date' => $startDate ?? '',
                'end_date' => $endDate ?? '',
            ],
            'properties' => $properties,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'start_date' => ['required', 'date'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $unit = Unit::with('rates')->findOrFail($validated['unit_id']);
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $durationMonths = (int) $validated['duration_months'];
        $endDate = $startDate->copy()->addMonthsNoOverflow($durationMonths)->format('Y-m-d');

        $reference = 'BK-' . strtoupper(Str::random(10));
        $cartToken = 'cart-admin-' . (string) Str::uuid();

        // Check if tenant with this phone exists
        $tenantId = Tenant::where('phone', $validated['guest_phone'])->value('id');

        BookingOrder::create([
            'cart_token' => $cartToken,
            'reference' => $reference,
            'unit_id' => $unit->id,
            'tenant_id' => $tenantId,
            'guest_name' => $validated['guest_name'],
            'guest_phone' => $validated['guest_phone'],
            'guest_email' => $validated['guest_email'] ?? null,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate,
            'duration_months' => $durationMonths,
            'amount' => $validated['amount'],
            'currency' => 'IDR',
            'status' => BookingOrder::STATUS_PENDING,
            'notes' => $validated['notes'] ?? null,
            'expires_at' => now()->addDays(2),
        ]);

        return redirect()->back()->with('success', "Cart order {$reference} created successfully.");
    }

    public function update(Request $request, BookingOrder $bookingOrder): RedirectResponse
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'start_date' => ['required', 'date'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:pending,paid,payment_conflict,expired,cancelled'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $durationMonths = (int) $validated['duration_months'];
        $endDate = $startDate->copy()->addMonthsNoOverflow($durationMonths)->format('Y-m-d');

        $tenantId = $bookingOrder->tenant_id ?: Tenant::where('phone', $validated['guest_phone'])->value('id');

        $bookingOrder->update([
            'unit_id' => $validated['unit_id'],
            'tenant_id' => $tenantId,
            'guest_name' => $validated['guest_name'],
            'guest_phone' => $validated['guest_phone'],
            'guest_email' => $validated['guest_email'] ?? null,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate,
            'duration_months' => $durationMonths,
            'amount' => $validated['amount'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', "Cart order {$bookingOrder->reference} updated successfully.");
    }

    public function destroy(BookingOrder $bookingOrder): RedirectResponse
    {
        $ref = $bookingOrder->reference;
        $bookingOrder->delete();

        return redirect()->back()->with('success', "Cart order {$ref} deleted successfully.");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:booking_orders,id'],
        ]);

        $count = BookingOrder::whereIn('id', $validated['ids'])->delete();

        return redirect()->back()->with('success', "{$count} cart orders deleted successfully.");
    }

    public function fulfill(Request $request, BookingOrder $bookingOrder, FulfillBookingOrder $fulfiller): RedirectResponse
    {
        if ($bookingOrder->isPaid()) {
            return redirect()->back()->with('info', "Cart order {$bookingOrder->reference} is already fulfilled.");
        }

        try {
            $user = $request->user();
            $adminIdentifier = 'ADMIN-MANUAL-' . ($user ? $user->id : '1');
            $fulfilled = $fulfiller->execute($bookingOrder, $adminIdentifier, now());

            if ($fulfilled->status === BookingOrder::STATUS_PAYMENT_CONFLICT) {
                return redirect()->back()->with('warning', "Warning: Room conflict detected for order {$fulfilled->reference}. Room is occupied.");
            }

            return redirect()->back()->with('success', "Cart order {$fulfilled->reference} fulfilled successfully! Lease #{$fulfilled->lease_id} created.");
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'Fulfillment failed: ' . $e->getMessage());
        }
    }
}
