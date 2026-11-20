<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class QuoteCatalog
{
    public const GROUPS = ['services' => 'Services', 'installations' => 'Installations', 'addons' => 'Add-ons'];

    public function configured(): array
    {
        $catalog = config('quote');

        foreach (DB::table('quote_catalog_overrides')->pluck('settings', 'item') as $key => $settings) {
            [$group, $id] = explode('/', $key, 2);

            if (isset($catalog[$group][$id])) {
                $catalog[$group][$id] = array_replace($catalog[$group][$id], json_decode($settings, true, flags: JSON_THROW_ON_ERROR));
            }
        }

        return $catalog;
    }

    public function active(): array
    {
        $catalog = $this->configured();

        foreach (array_keys(self::GROUPS) as $group) {
            $catalog[$group] = array_filter($catalog[$group], static fn (array $item): bool => $item['enabled'] ?? true);
        }
        if (! $catalog['installations']) {
            unset($catalog['services']['installation']);
        }

        $catalog['goals'] = array_filter($catalog['goals'], static fn (array $rule, string $id): bool => $id === 'appointments' || isset($catalog['services'][$rule['service']]), ARRAY_FILTER_USE_BOTH);
        $catalog['appointment_answers'] = array_filter($catalog['appointment_answers'], static fn (array $rule): bool => isset($catalog['services'][$rule['service']])
            && (! isset($rule['installation']) || isset($catalog['installations'][$rule['installation']])));
        if (! $catalog['appointment_answers']) {
            unset($catalog['goals']['appointments']);
        }

        return $catalog;
    }

    public function discoveryUrl(): ?string
    {
        $url = config('quote.discovery_url');

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;
    }
}
