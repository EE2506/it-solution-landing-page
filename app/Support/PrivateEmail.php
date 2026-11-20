<?php

namespace App\Support;

class PrivateEmail
{
    public static function lookup(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), config('quote.lookup_key') ?: config('app.key'));
    }
}
