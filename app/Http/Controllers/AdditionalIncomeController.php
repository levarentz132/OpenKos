<?php

namespace App\Http\Controllers;

use App\Models\AdditionalIncome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdditionalIncomeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $category = $request->query('category');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $incomes = AdditionalIncome::query()
            ->with(['recordedBy:id,name'])
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('notes', 'like', "%{$search}%"))
            ->when($category && $category !== 'all', fn ($q) => $q->where('category', $category))
            ->when($startDate, fn ($q) => $q->whereDate('income_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('income_date', '<=', $endDate))
            ->latest('income_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $totalAmount = (float) AdditionalIncome::query()
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('notes', 'like', "%{$search}%"))
            ->when($category && $category !== 'all', fn ($q) => $q->where('category', $category))
            ->when($startDate, fn ($q) => $q->whereDate('income_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('income_date', '<=', $endDate))
            ->sum('amount');

        return Inertia::render('additional-incomes/index', [
            'incomes' => $incomes,
            'total_amount' => $totalAmount,
            'filters' => [
                'search' => $search ?? '',
                'category' => $category ?? 'all',
                'start_date' => $startDate ?? '',
                'end_date' => $endDate ?? '',
            ],
            'categories' => [
                ['value' => 'laundry', 'label' => 'Laundry Services'],
                ['value' => 'vending', 'label' => 'Vending / Kiosk'],
                ['value' => 'parking', 'label' => 'Parking Fees'],
                ['value' => 'services', 'label' => 'Additional Services'],
                ['value' => 'other', 'label' => 'Other Business Income'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'income_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['recorded_by'] = $request->user()?->id;

        AdditionalIncome::create($validated);

        return redirect()->back()->with('success', 'Additional income recorded successfully.');
    }

    public function update(Request $request, AdditionalIncome $additionalIncome): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'income_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $additionalIncome->update($validated);

        return redirect()->back()->with('success', 'Additional income updated successfully.');
    }

    public function destroy(AdditionalIncome $additionalIncome): RedirectResponse
    {
        $additionalIncome->delete();

        return redirect()->back()->with('success', 'Additional income entry deleted.');
    }
}
