@extends('owner.layout')
@section('content')
    <h1>Estimate catalog</h1>
    <p>Updates apply to new estimates. Saved estimates retain their original prices and scope.</p>
    <p>Enter rates in pesos. Leave the rate blank for work that needs review.</p>

    @foreach ($groups as $group => $label)
        <section class="catalog-section" aria-labelledby="catalog-{{ $group }}">
            <h2 id="catalog-{{ $group }}">{{ $label }}</h2>
            @foreach ($catalog[$group] as $id => $item)
                <details id="{{ $group }}-{{ $id }}" class="catalog-item" @if(session('catalog_item') === $group.'-'.$id) open @endif>
                    <summary>{{ $item['name'] }} <small>{{ ($item['enabled'] ?? true) ? 'Available' : 'Retired' }}</small></summary>
                    <form method="post" action="{{ route('owner.catalog.update', [$group, $id]) }}" class="field-stack">
                        @csrf
                        @method('PATCH')
                        <label>Availability
                            <select name="enabled">
                                <option value="1" @selected($item['enabled'] ?? true)>Available</option>
                                <option value="0" @selected(! ($item['enabled'] ?? true))>Retired</option>
                            </select>
                        </label>
                        @if ($group !== 'services' || $id !== 'installation')
                            <label>Rate (₱) <input name="rate" type="text" inputmode="decimal" maxlength="11" value="{{ $item['rate'] === null ? '' : number_format($item['rate'] / 100, 2, '.', '') }}"></label>
                            <label>Priority eligibility
                                <select name="rush"><option value="0" @selected(! $item['rush'])>Ineligible</option><option value="1" @selected($item['rush'])>Eligible</option></select>
                            </label>
                        @else
                            <input type="hidden" name="rush" value="0">
                        @endif
                        @if ($group === 'services')
                            <label>Included essentials, one per line
                                <textarea name="includes" rows="5" maxlength="3000" required>{{ implode("\n", $item['includes']) }}</textarea>
                            </label>
                        @endif
                        <button>Save {{ $item['name'] }}</button>
                    </form>
                </details>
            @endforeach
        </section>
    @endforeach
@endsection
