@props(['project', 'opensAt', 'auditionMonth'])

<dl {{ $attributes->class('casting-calendar') }}>
    <div>
        <dt>Candidature online</dt>
        <dd>Dal <x-casting-opening-time :opens-at="$opensAt" /></dd>
    </div>
    <div>
        <dt>Audizioni in presenza</dt>
        <dd>{{ $project['audition_city'] }}, <time datetime="{{ $project['audition_month'] }}">{{ $auditionMonth }}</time></dd>
    </div>
    <div>
        <dt>Impegno richiesto</dt>
        <dd>{{ $project['engagement'] }}</dd>
    </div>
</dl>
