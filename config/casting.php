<?php
return [
    'uploads_per_hour'=>1,
    'local_access_only'=>(bool)env('CASTING_LOCAL_ACCESS_ONLY',true),
    /** L'identità legale viene completata soltanto con i dati confermati dal titolare. */
    'privacy'=>['controller'=>null,'contact'=>null],
    'project'=>[
        'title'=>'NERO',
        'description'=>'NERO. Audizioni per il cast.',
        'staff'=>[
            ['role'=>'Regia','name'=>null],
            ['role'=>'Scenografia','name'=>null],
            ['role'=>'Costumi','name'=>null],
            ['role'=>'Coreografie','name'=>null],
        ],
        'profiles'=>null,
        'location'=>null,
        'calendar'=>null,
    ],
];
