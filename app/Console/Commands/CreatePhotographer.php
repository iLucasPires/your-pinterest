<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreatePhotographer extends Command
{
    protected $signature = 'photographer:create
                            {--name= : Full name}
                            {--email= : Email address}
                            {--password= : Password}';

    protected $description = 'Create a new photographer account';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Name');
        $email = $this->option('email') ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email [{$email}] already exists.");

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->info("Photographer [{$name}] created. You can now log in at /admin.");

        return self::SUCCESS;
    }
}
