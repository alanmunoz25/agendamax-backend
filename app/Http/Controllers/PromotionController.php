<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Promotion;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Promotion::class);

        $promotions = Promotion::query()
            ->when(request('search'), fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when(request('is_active') !== null, fn ($query) => $query->where('is_active', request('is_active') === '1'))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Promotion $promotion) => [
                ...$promotion->toArray(),
                'image_url' => $promotion->image_url,
            ]);

        return Inertia::render('Promotions/Index', [
            'promotions' => $promotions,
            'filters' => request()->only(['search', 'is_active']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Promotion::class);

        return Inertia::render('Promotions/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $this->authorize('create', Promotion::class);

        $data = $request->validated();

        $imagePath = $request->file('image')->store('promotions', 'public');

        Promotion::create([
            'business_id' => auth()->user()->primary_business_id,
            'title' => $data['title'],
            'image_path' => $imagePath,
            'url' => $data['url'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('promotions.index')
            ->with('success', 'Promotion created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Promotion $promotion): Response
    {
        $this->authorize('update', $promotion);

        return Inertia::render('Promotions/Edit', [
            'promotion' => [
                ...$promotion->toArray(),
                'image_url' => $promotion->image_url,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $this->authorize('update', $promotion);

        $data = $request->validated();

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($promotion->image_path);
            $data['image_path'] = $request->file('image')->store('promotions', 'public');
        }

        unset($data['image']);

        $promotion->update($data);

        return redirect()->route('promotions.index')
            ->with('success', 'Promotion updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Promotion $promotion): RedirectResponse
    {
        $this->authorize('delete', $promotion);

        Storage::disk('public')->delete($promotion->image_path);
        $promotion->delete();

        return redirect()->route('promotions.index')
            ->with('success', 'Promotion deleted successfully.');
    }
}
