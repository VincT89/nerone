@props(['id', 'label' => 'Password', 'name' => 'password', 'autocomplete' => 'current-password'])
<div class="password-field">
    <label for="{{ $id }}">{{ $label }}</label>
    <div class="password-control">
        <input {{ $attributes->merge(['id' => $id, 'name' => $name, 'type' => 'password', 'autocomplete' => $autocomplete]) }}>
        <button type="button" class="password-toggle" data-password-toggle aria-controls="{{ $id }}" aria-label="Mostra la password" aria-pressed="false" hidden>
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path d="m3 3 18 18" data-password-slash hidden/></svg>
        </button>
    </div>
</div>
