<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteRequest extends Model
{
    protected $fillable = ['reference', 'request_key', 'email_lookup', 'contact', 'snapshot', 'status', 'internal_notes'];

    protected function casts(): array
    {
        return ['contact' => 'encrypted:array', 'snapshot' => 'encrypted:array', 'internal_notes' => 'encrypted'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(QuoteDelivery::class);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
