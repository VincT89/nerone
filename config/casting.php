<?php
return [
    'local_access_only'=>(bool)env('CASTING_LOCAL_ACCESS_ONLY',true),
    /** L'identità legale viene completata soltanto con i dati confermati dal titolare. */
    'privacy'=>['controller'=>null,'contact'=>null],
    'project'=>[
        'title'=>'NERONE',
        'description'=>'L’immaginario dell’antica Roma incontra un linguaggio scenico contemporaneo.',
        'profiles'=>null,
        'location'=>null,
        'calendar'=>null,
    ],
];
