<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromotionResource;
use App\Models\Business;
use App\Models\Promotion;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromotionController extends Controller
{
    /**
     * List active, non-expired promotions for a business.
     */
    public function index(int $businessId): AnonymousResourceCollection
    {
        $business = Business::findOrFail($businessId);

        $promotions = Promotion::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->active()
            ->orderByDesc('created_at')
            ->get();

        return PromotionResource::collection($promotions);
    }
}
