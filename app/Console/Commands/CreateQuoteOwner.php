<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PrivateEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateQuoteOwner extends Command
{
    protected $signature = 'quotes:create-owner {email}';

    protected $description = 'Create an owner account for the quote dashboard';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email_lookup', PrivateEmail::lookup($email))->exists()) {
            $this->error('Use a valid email address that is not already registered.');

            return self::FAILURE;
        }
        $password = $this->secret('Password (12 or more characters)');
        if (! is_string($password) || strlen($password) < 12 || $password !== $this->secret('Confirm password')) {
            $this->error('The password must be at least 12 characters and match its confirmation.');

            return self::FAILURE;
        }

        $owner = new User(['name' => 'Owner', 'email' => $email, 'password' => Hash::make($password)]);
        $owner->forceFill(['is_owner' => true])->save();
        $this->info('Owner account created.');

        return self::SUCCESS;
    }
}
