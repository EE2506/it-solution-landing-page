<?php

namespace App\Services;

class BudgetComparison
{
    public function compare(Estimate $estimates, array $input, array $catalog, int $budget, array $essential): array
    {
        $current = $estimates->build($input, $catalog);
        $choices = [];
        foreach ($current['one_time'] as $item) {
            if (isset($catalog['addons'][$item['id']])) {
                $choices[$item['id']] = $item['name'];
            }
        }
        if ($current['rush'] > 0) {
            $choices['priority'] = 'Priority delivery';
        }
        $options = [];

        if ($current['total'] > $budget) {
            $subsets = [[]];
            foreach (array_diff_key($choices, array_flip($essential)) as $id => $name) {
                // The catalog has five upgrades and priority, so all combinations fit in one request.
                $subsets = array_merge($subsets, array_map(static fn (array $subset): array => $subset + [$id => $name], $subsets));
            }

            foreach (array_slice($subsets, 1) as $deferrals) {
                $after = $input;
                foreach (array_keys($deferrals) as $id) {
                    if ($id === 'priority') {
                        $after['priority'] = false;
                        $after['priority_later'] = true;
                    } else {
                        $after['addons'][$id]['state'] = 'later';
                    }
                }

                $estimate = $estimates->build($after, $catalog);
                if ($estimate['total'] >= $current['total'] || count($estimate['review']) > count($current['review'])) {
                    continue;
                }

                $options[] = [
                    'defer' => $deferrals,
                    'total' => $estimate['total'],
                    'savings' => $current['total'] - $estimate['total'],
                    'fits' => $estimate['total'] <= $budget,
                    'remaining' => array_values(array_map(static fn (array $line): string => $line['name'], $estimate['one_time'])),
                    'review' => array_column($estimate['review'], 'name'),
                ];
            }
            usort($options, static fn (array $a, array $b): int => $a['fits'] && $b['fits']
                ? [count($a['defer']), -$a['total']] <=> [count($b['defer']), -$b['total']]
                : [$b['fits'], $a['total']] <=> [$a['fits'], $b['total']]);
        }

        return [
            'choices' => $choices,
            'total' => $current['total'],
            'review' => array_column($current['review'], 'name'),
            'options' => array_slice($options, 0, 2),
        ];
    }
}
