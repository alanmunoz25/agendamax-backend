<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Inertia\Inertia;
use Inertia\Response;

class PublicDiscoverController extends Controller
{
    /**
     * The available sectors for discovery filtering.
     *
     * @var array<int, string>
     */
    private const SECTORS = [
        'Salón',
        'Spa',
        'Barbería',
        'Consultorio',
        'Otro',
    ];

    /**
     * The Dominican Republic provinces for filtering.
     *
     * @var array<int, string>
     */
    private const PROVINCES = [
        'Distrito Nacional',
        'Santo Domingo',
        'Santiago',
        'La Vega',
        'San Cristóbal',
        'La Altagracia',
        'Duarte',
        'Peravia',
        'Puerto Plata',
        'Espaillat',
        'Hermanas Mirabal',
        'Valverde',
        'Montecristi',
        'Dajabón',
        'Santiago Rodríguez',
        'Elías Piña',
        'San Juan',
        'Azua',
        'Baoruco',
        'Barahona',
        'Independencia',
        'Pedernales',
        'Hato Mayor',
        'El Seibo',
        'La Romana',
        'San Pedro de Macorís',
        'Monte Plata',
        'Sánchez Ramírez',
        'María Trinidad Sánchez',
        'Samaná',
    ];

    /**
     * Display the public business discovery page with initial results.
     */
    public function index(): Response
    {
        $businesses = Business::where('status', 'active')
            ->withCount(['services', 'employees'])
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('Public/Discover', [
            'businesses' => $businesses,
            'sectors' => self::SECTORS,
            'provinces' => self::PROVINCES,
        ]);
    }
}
