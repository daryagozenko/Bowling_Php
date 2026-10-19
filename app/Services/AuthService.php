<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Зарегистрировать нового клиента и сразу войти под ним.
     *
     * @param  array{name: string, email: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function register(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],  // хеш ставит casts() в модели
            'role' => 'client',                // регистрируются только клиенты
        ]);

        Auth::login($user);

        return $user;
    }

    /**
     * Попытаться войти по email и паролю.
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, bool $remember = false): User
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Неверный email или пароль.',
            ]);
        }

        // Обновляем сессию — защита от session fixation.
        request()->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function logout(): void
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}