<?php

namespace App\Http\Controllers;

use App\Models\AuditionComment;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductionTeamController extends Controller
{
    private const ROLES = [
        'collaborator' => 'Collaboratore',
        'admin' => 'Amministratore',
    ];

    public function index(): View
    {
        $operators = User::where('is_casting_operator', true)->orderBy('name')->orderBy('id')->get();

        return view('casting.team', [
            'operators' => $operators,
            'roles' => self::ROLES,
            'adminCount' => $operators->where('is_casting_admin', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => [
                'bail', 'required', 'string', 'confirmed', 'max:72', Password::min(12)->letters()->numbers(),
                static function (string $attribute, #[\SensitiveParameter] string $value, Closure $fail): void {
                    /** BCrypt limita la password a 72 byte, anche con caratteri multibyte. */
                    if (strlen($value) > 72) {
                        $fail('La password supera la lunghezza consentita. Scegli una password più breve.');
                    }
                },
            ],
            'role' => ['sometimes', 'required', 'string', Rule::in(array_keys(self::ROLES))],
        ]);

        try {
            DB::transaction(function () use ($request, $data): void {
                $this->lockAdministrators($request);
                User::forceCreate([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'is_casting_operator' => true,
                    'is_casting_admin' => ($data['role'] ?? 'collaborator') === 'admin',
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Questo indirizzo email è già in uso.']);
        } catch (QueryException) {
            /** Evita di includere l'hash nei dettagli dell'errore SQL. */
            throw ValidationException::withMessages(['account' => 'Impossibile creare l’account. Riprova.']);
        }

        return redirect()->route('casting.team')->with('saved', 'Account creato. Comunica le credenziali direttamente alla persona.');
    }

    public function update(Request $request, User $operator): RedirectResponse
    {
        abort_unless($operator->is_casting_operator, 404);
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:254', Rule::unique('users')->ignore($operator)],
            'role' => ['sometimes', 'required', 'string', Rule::in(array_keys(self::ROLES))],
        ]);

        try {
            DB::transaction(function () use ($request, $operator, $data): void {
                $adminCount = $this->lockAdministrators($request);
                $current = User::where('is_casting_operator', true)->lockForUpdate()->findOrFail($operator->id);
                $current->fill(Arr::only($data, ['name', 'email']));

                if (isset($data['role'])) {
                    if ($data['role'] === 'collaborator') {
                        $this->ensureOtherAdmin($current, $adminCount);
                    }
                    $current->is_casting_admin = $data['role'] === 'admin';
                }
                $current->save();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Questo indirizzo email è già in uso.']);
        }

        $destination = $request->user()->is($operator) && ($data['role'] ?? null) === 'collaborator' ? 'casting.index' : 'casting.team';

        return redirect()->route($destination)->with('saved', 'Account aggiornato.');
    }

    public function edit(User $operator): View
    {
        abort_unless($operator->is_casting_operator, 404);

        return view('casting.team-edit', [
            'operator' => $operator,
            'roles' => self::ROLES,
            'roleLocked' => $operator->is_casting_admin && !User::where('is_casting_operator', true)
                ->where('is_casting_admin', true)->whereKeyNot($operator->id)->exists(),
        ]);
    }

    public function confirmDelete(Request $request, User $operator): View
    {
        abort_unless($operator->is_casting_operator, 404);
        abort_if($request->user()->is($operator), 403);

        return view('casting.team-delete', ['operator' => $operator]);
    }

    public function destroy(Request $request, User $operator): RedirectResponse
    {
        abort_if($request->user()->is($operator), 403);
        $request->validate(['confirmation' => ['accepted']], [
            'confirmation.accepted' => 'Conferma l’eliminazione dell’account.',
        ]);

        DB::transaction(function () use ($request, $operator): void {
            $adminCount = $this->lockAdministrators($request);
            $current = User::where('is_casting_operator', true)->lockForUpdate()->findOrFail($operator->id);
            $this->ensureOtherAdmin($current, $adminCount);

            /** I commenti verrebbero scollegati dal database. I voti vengono rimossi dal vincolo esistente. */
            AuditionComment::where('user_id', $current->id)->delete();
            $current->delete();
        }, 3);

        return redirect()->route('casting.team')->with('saved', 'Account, voti e commenti eliminati.');
    }

    private function lockAdministrators(Request $request): int
    {
        /** Serializza i cambi di ruolo e ricontrolla il permesso di chi ha inviato la richiesta. */
        $administrators = User::where('is_casting_operator', true)->where('is_casting_admin', true)
            ->orderBy('id')->lockForUpdate()->get(['id', 'is_casting_operator', 'is_casting_admin']);

        Gate::forUser($administrators->find($request->user()->getKey()))->authorize('manage-casting');

        return $administrators->count();
    }

    private function ensureOtherAdmin(User $operator, int $adminCount): void
    {
        if ($operator->is_casting_admin && $adminCount < 2) {
            throw ValidationException::withMessages(['account' => 'Deve restare almeno un amministratore.']);
        }
    }
}
