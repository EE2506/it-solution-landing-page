<?php

namespace App\Console\Commands;

use App\Models\QuoteDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class DeliverQuotes extends Command
{
    protected $signature = 'quotes:deliver';

    protected $description = 'Send pending quote estimates and retry transient failures';

    public function handle(): int
    {
        return Cache::lock('quote-delivery-worker', 600)->get(function (): int {
            $owner = config('quote.owner_email');
            if (! filter_var($owner, FILTER_VALIDATE_EMAIL)) {
                $this->error('Set a valid QUOTE_OWNER_EMAIL.');

                return self::FAILURE;
            }

            $pending = QuoteDelivery::whereNull('sent_at')->whereNull('failed_at')
                ->where('available_at', '<=', now())->orderBy('id')->limit(10)->get();
            foreach ($pending as $delivery) {
                $this->deliver($delivery, $owner);
            }

            return self::SUCCESS;
        }) ?? self::SUCCESS;
    }

    private function deliver(QuoteDelivery $delivery, string $owner): void
    {
        $quote = $delivery->quoteRequest;
        $recipient = $delivery->recipient === 'owner' ? $owner : $quote->contact['email'];

        try {
            Mail::send('mail.quote', [
                'quote' => $quote,
                'isOwner' => $delivery->recipient === 'owner',
            ], function ($message) use ($recipient, $quote): void {
                $message->to($recipient)->subject('Project estimate '.$quote->reference);
            });
            $delivery->forceFill(['sent_at' => now()])->save();
        } catch (TransportExceptionInterface) {
            $attempts = $delivery->attempts + 1;
            $delivery->forceFill([
                'attempts' => $attempts,
                'available_at' => now()->addMinutes(2 ** $attempts),
                'failed_at' => $attempts >= 5 ? now() : null,
            ])->save();
            Log::warning('Quote email transport failed', ['reference' => $quote->reference, 'recipient' => $delivery->recipient, 'attempt' => $attempts]);
        }
    }
}
