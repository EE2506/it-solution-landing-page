<?php

namespace App\Http\Controllers;

use App\Services\QuoteCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(QuoteCatalog $catalog): View
    {
        return view('owner.catalog', [
            'groups' => QuoteCatalog::GROUPS,
            'catalog' => $catalog->configured(),
        ]);
    }

    public function update(Request $request, string $group, string $item): RedirectResponse
    {
        abort_unless(isset(QuoteCatalog::GROUPS[$group], config('quote')[$group][$item]), 404);

        $values = $request->validate([
            'rate' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'enabled' => ['required', 'boolean'],
            'rush' => ['required', 'boolean'],
            'includes' => [$group === 'services' ? 'required' : 'prohibited', 'nullable', 'string', 'max:3000'],
        ]);

        $rate = null;
        if (isset($values['rate'])) {
            [$pesos, $fraction] = array_pad(explode('.', $values['rate'], 2), 2, '');
            $rate = (int) $pesos * 100 + (int) str_pad($fraction, 2, '0');
        }
        $settings = [
            'enabled' => (bool) $values['enabled'],
            'rush' => (bool) $values['rush'],
            'rate' => $rate,
        ];
        if ($group === 'services') {
            $settings['includes'] = array_values(array_filter(array_map('trim', preg_split('/\R/', $values['includes'])), static fn (string $line): bool => $line !== ''));
        }
        if ($group === 'services' && $item === 'installation') {
            // Installations are priced by task, and the service is only their entry point.
            unset($settings['rate'], $settings['rush']);
        }

        DB::table('quote_catalog_overrides')->upsert([
            'item' => "$group/$item",
            'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
        ], ['item'], ['settings']);
        Log::info('Owner updated quote catalog', ['owner' => $request->user()->id, 'group' => $group, 'item' => $item]);

        $anchor = $group.'-'.$item;

        return redirect(route('owner.catalog').'#'.$anchor)->with(['message' => 'Catalog item saved.', 'catalog_item' => $anchor]);
    }
}
