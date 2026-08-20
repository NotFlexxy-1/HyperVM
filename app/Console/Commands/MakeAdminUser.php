<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class MakeAdminUser extends Command
{
    protected $signature = 'hypervm:make-admin
        {--email= : Email address}
        {--username= : Username}
        {--name= : Display name}
        {--password= : Password (generated when omitted)}';

    protected $description = 'Create (or promote) an administrator account.';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email address');
        $username = $this->option('username') ?: $this->ask('Username');
        $name = $this->option('name') ?: $this->ask('Display name', $username);
        $password = $this->option('password') ?: Str::password(20);

        $validator = Validator::make(compact('email', 'username', 'password'), [
            'email' => ['required', 'email'],
            'username' => ['required', 'alpha_dash', 'min:3'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
        );

        $user->syncRoles(['admin']);

        $this->info("Administrator {$user->email} is ready.");

        if (! $this->option('password')) {
            $this->warn("Generated password: {$password}");
        }

        return self::SUCCESS;
    }
}
