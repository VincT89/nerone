<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CastingAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('is_casting_operator', true)->where('is_casting_admin', true)->exists()) {
            $this->command?->info('Esiste già un amministratore del casting.');

            return;
        }

        $password = config('casting.admin_password');
        if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
            throw new RuntimeException('Imposta una password da 12 a 72 byte in CASTING_ADMIN_PASSWORD prima di eseguire il seeder.');
        }

        try {
            User::forceCreate([
                'name' => 'Amministratore iniziale',
                'email' => 'admin@admin.it',
                'password' => Hash::make($password),
                'is_casting_operator' => true,
                'is_casting_admin' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new RuntimeException('L’indirizzo dell’amministratore iniziale è già in uso.');
        } catch (QueryException) {
            /** Il messaggio SQL può contenere l’hash. */
            throw new RuntimeException('Impossibile creare l’amministratore iniziale. Verifica il database.');
        }

        $this->command?->info('Amministratore iniziale creato.');
    }
}
