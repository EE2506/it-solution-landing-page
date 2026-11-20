<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteDelivery extends Model
{
    public $timestamps = false;

    protected $fillable = ['recipient', 'available_at'];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function state(): string
    {
        return $this->sent_at ? 'Sent' : ($this->failed_at ? 'Failed' : 'Pending');
    }
}
