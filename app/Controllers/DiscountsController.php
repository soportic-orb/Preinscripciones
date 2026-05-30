<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Models\Course;
use App\Models\Discount;

/**
 * CRUD de descuentos, becas y códigos promocionales (solo admin).
 */
final class DiscountsController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('management/discounts/index', [
            'title' => __('discounts.title'),
            'user' => Auth::user(),
            'discounts' => Discount::all(),
        ]);
    }

    public function create(Request $request): never
    {
        $this->view('management/discounts/edit', [
            'title' => __('discounts.new'),
            'user' => Auth::user(),
            'courses' => Course::all(),
        ]);
    }

    public function store(Request $request): never
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        $id = Discount::store([
            'code' => $code !== '' ? $code : null,
            'name' => $request->str('name'),
            'type' => in_array($request->input('type'), Discount::TYPES, true) ? $request->input('type') : 'percent',
            'value' => (float) $request->input('value', 0),
            'scope' => in_array($request->input('scope'), Discount::SCOPES, true) ? $request->input('scope') : 'all',
            'scope_id' => (int) $request->input('scope_id', 0) ?: null,
            'valid_from' => $request->str('valid_from') ?: null,
            'valid_to' => $request->str('valid_to') ?: null,
            'max_uses' => (int) $request->input('max_uses', 0),
            'is_active' => $request->input('is_active') ? 1 : 0,
        ]);
        Audit::log('discount.create', Auth::id(), 'discount', $id, ['code' => $code], $request->ip());
        Flash::success(__('discounts.saved'));
        $this->redirect('/gestion/descuentos');
    }
}
