<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class Estimate
{
    public function build(array $input): array
    {
        $catalog = config('quote');
        $goal = $catalog['goals'][$input['goal'] ?? 'not_sure'];
        $recommendation = ($input['goal'] ?? null) === 'appointments'
            ? ($catalog['appointment_answers'][$input['appointment'] ?? 'unknown'])
            : $goal;

        $service = $input['mode'] === 'direct' ? $input['service'] : $recommendation['service'];
        $package = $catalog['services'][$service];
        $installation = $service === 'installation'
            ? ($input['installation'] ?? $recommendation['installation'] ?? null)
            : null;
        $oneTime = [];
        $later = [];
        $review = [];
        $included = $package['includes'];
        $provider = [];
        if ($service === 'website') {
            $provider[] = $catalog['provider']['hosting'];
        }

        if ($service === 'installation') {
            if ($installation === null) {
                throw ValidationException::withMessages(['installation' => 'Choose what you need installed.']);
            }
            $work = $catalog['installations'][$installation];
            $included[] = $work['description'];
            $this->addItem($oneTime, $review, $installation, $work, 1);
            if ($installation === 'scheduler') {
                $provider[] = $catalog['provider']['booking'];
            }
        } else {
            $this->addItem($oneTime, $review, $service, $package, 1);
        }

        foreach ($catalog['addons'] as $id => $item) {
            $selection = $input['addons'][$id] ?? null;
            if ($selection === null || $selection['state'] === 'removed') {
                continue;
            }
            if (isset($item['services']) && ! in_array($service, $item['services'], true)) {
                continue;
            }

            $quantity = $selection['quantity'];
            if ($quantity > $item['max']) {
                throw ValidationException::withMessages(["addons.$id.quantity" => 'Choose a supported quantity.']);
            }
            if ($selection['state'] === 'later') {
                $later[] = ['id' => $id, 'name' => $item['name'], 'scope' => $item['description'], 'quantity' => $quantity];

                continue;
            }

            if ($id === 'booking' && $installation === 'scheduler') {
                $included[] = 'Scheduler connection included with the installation';

                continue;
            }
            if ($id === 'email' && $service === 'website' && ($input['email_purpose'] ?? '') === 'inquiry') {
                continue;
            }
            if ($id === 'booking' && ($input['booking_tool'] ?? '') !== 'supported') {
                $item['rate'] = null;
                $item['description'] = 'Confirm which scheduler you use and whether it can be connected';
            }
            if ($id === 'sms' && ($input['sms_source'] ?? '') === 'already_sends') {
                $included[] = 'Your existing booking tool already sends the requested reminders';

                continue;
            }
            if ($id === 'sms' && ! in_array($input['sms_source'] ?? '', ['booking', 'calendar', 'other_event'], true)) {
                $item['rate'] = null;
                $item['description'] = 'Confirm which tool records the appointment time or event before pricing reminder setup';
            }
            if ($id === 'booking' || ($id === 'sms' && $item['rate'] !== null)) {
                $provider[] = $catalog['provider'][$id];
            }
            $this->addItem($oneTime, $review, $id, $item, $quantity);
        }

        $rushBase = array_sum(array_column(array_filter($oneTime, fn (array $item) => $item['rush']), 'amount'));
        $rush = ($input['priority'] ?? false) ? intdiv($rushBase * $catalog['rush_percent'] + 50, 100) : 0;
        if (! ($input['priority'] ?? false) && ($input['priority_later'] ?? false)) {
            $later[] = ['id' => 'priority', 'name' => 'Priority delivery', 'scope' => 'Subject to availability', 'quantity' => 1];
        }
        $monthly = [];
        foreach ($catalog['months'][$service] ?? [] as $id => $item) {
            if (in_array($id, $input['monthly'] ?? [], true)) {
                $monthly[] = ['id' => $id] + $item;
            }
        }

        $features = [];
        if ($input['mode'] === 'guided') {
            foreach (array_intersect($goal['features'] ?? [], $input['features'] ?? []) as $feature) {
                $features[] = $catalog['feature_choices'][$feature];
            }
        }
        if ($service === 'system' && isset($input['staff_count'])) {
            $features[] = 'Staff using the system: '.$input['staff_count'];
        }

        return [
            'service' => $service,
            'installation' => $installation,
            'name' => $installation ? $catalog['installations'][$installation]['name'] : $package['name'],
            'why' => $input['mode'] === 'guided' ? $recommendation['why'] : $package['description'],
            'question' => $input['mode'] === 'guided' ? ($recommendation['question'] ?? null) : null,
            'included' => $included,
            'features' => $features,
            'one_time' => $oneTime,
            'monthly' => $monthly,
            'provider' => array_values(array_unique($provider)),
            'review' => $review,
            'later' => $later,
            'rush_percent' => $catalog['rush_percent'],
            'rush' => $rush,
            'total' => array_sum(array_column($oneTime, 'amount')) + $rush,
            'currency' => 'PHP',
        ];
    }

    private function addItem(array &$priced, array &$review, string $id, array $item, int $quantity): void
    {
        $line = [
            'id' => $id, 'name' => $item['name'], 'scope' => $item['description'],
            'quantity' => $quantity, 'rate' => $item['rate'],
        ];
        if ($item['rate'] === null) {
            $review[] = $line;

            return;
        }
        $priced[] = $line + ['amount' => $item['rate'] * $quantity, 'rush' => $item['rush']];
    }
}
