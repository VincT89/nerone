@extends('layout')
@section('title','Collaboratori')
@section('body-class','casting-page')
@section('casting-nav','yes')
@section('content')
<div class="production-shell">
    @include('casting.navigation')
    <header class="production-heading">
        <div>
            <h1>Collaboratori</h1>
            <p>Gestisci gli accessi e i ruoli di chi valuta le candidature.</p>
        </div>
    </header>
    @include('candidate.errors')

    <div class="production-team">
        <section aria-labelledby="team-members">
            <h2 id="team-members">Il gruppo di valutazione</h2>
            <ul>
                @foreach($operators as $operator)
                    @php($role = $operator->is_casting_admin ? 'admin' : 'collaborator')
                    <li>
                        <strong>{{ $operator->name }}</strong>
                        <span>{{ $operator->email }}</span>
                        <small>{{ $roles[$role] }}</small>
                        <div class="team-member-actions">
                            <a class="button" href="{{ route('casting.team.edit', $operator) }}">Modifica<span class="sr-only"> {{ $operator->name }} ({{ $operator->email }})</span></a>
                            @if(auth()->user()->is($operator))
                                <button class="button danger" type="button" disabled aria-describedby="team-delete-reason-{{ $operator->id }}">Elimina</button>
                            @else
                                <a class="button danger" href="{{ route('casting.team.confirm-delete', $operator) }}">Elimina<span class="sr-only"> {{ $operator->name }} ({{ $operator->email }})</span></a>
                            @endif
                        </div>
                        @if(auth()->user()->is($operator))
                            <small id="team-delete-reason-{{ $operator->id }}">
                                {{ $adminCount === 1 ? 'Per eliminare questo account, crea prima un secondo amministratore e accedi con quello.' : 'Per eliminare questo account, accedi con un altro amministratore.' }}
                            </small>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <form action="{{ route('casting.team.store') }}" method="post">
            @csrf
            <h2>Crea account</h2>
            <label>Nome<input name="name" value="{{ old('name') }}" maxlength="100" required autocomplete="name"></label>
            <label>Email<input name="email" type="email" value="{{ old('email') }}" maxlength="254" required autocomplete="username"></label>
            <label>
                Ruolo
                <select name="role" required>
                    @foreach($roles as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', 'collaborator') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <x-password-field id="team-password" autocomplete="new-password" minlength="12" maxlength="72" required />
            <x-password-field id="team-password-confirmation" label="Ripeti la password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" required />
            <p>Almeno 12 caratteri, con lettere e numeri. Comunica le credenziali direttamente alla persona.</p>
            <button type="submit" class="button primary">Crea account</button>
        </form>
    </div>
</div>
@endsection
