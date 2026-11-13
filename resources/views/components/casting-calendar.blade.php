@props(['project', 'opensAt', 'deadline', 'isOpen', 'isUpcoming', 'auditionMonth'])

<dl {{ $attributes->class('casting-calendar') }}>
    <div>
        <dt>Candidature online</dt>
        @if($isUpcoming)
            <dd>Dal <x-casting-opening-time :opens-at="$opensAt" /></dd>
        @elseif($isOpen)
            <dd>Aperte @if($deadline) fino al <x-casting-opening-time :opens-at="$deadline" :show-time="true" />@endif</dd>
        @else
            <dd>Chiuse</dd>
        @endif
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
