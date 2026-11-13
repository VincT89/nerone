<?php
return [
    'uploads_per_hour'=>1,
    'upload_attempts_per_quarter_hour'=>4,
    'ffmpeg_binary'=>env('CASTING_FFMPEG_BINARY','ffmpeg'),
    'declaration'=>[
        'version'=>'2026-11-12',
        'text'=>'Confermo che le informazioni fornite sono corrette e di essere autorizzato/a a presentare tutti i materiali inclusi nella candidatura.',
    ],
    'local_access_only'=>(bool)env('CASTING_LOCAL_ACCESS_ONLY',true),
    'admin_password'=>env('CASTING_ADMIN_PASSWORD'),
    'privacy'=>['controller'=>'Nero Musical srl','contact'=>null],
    'project'=>[
        'title'=>'NERO',
        'description'=>'NERO! è un nuovo musical in preparazione a Roma.',
        'opening_date'=>'2026-11-17',
        'audition_city'=>'Roma',
        'audition_month'=>'2026-12',
        'engagement'=>'Da metà maggio a ottobre 2027',
        'profiles'=>'Cerchiamo interpreti per ruoli principali e cover, personaggi minori ed ensemble, con solide competenze nel canto, nella recitazione e nel movimento. Sono richieste vocalità pop, rock e soul e un’ottima padronanza dell’inglese.',
        'authors'=>[
            [
                'role'=>'Soggetto e libretto',
                'name'=>'Franco Migliacci',
                'collaborators'=>'Scritto con Ernesto Migliacci in collaborazione con Gino Landi.',
            ],
            ['role'=>'Liriche e libretto in inglese','name'=>'Chuck Rolando'],
        ],
        'staff'=>[
            ['role'=>'Regia e coreografie','name'=>'Laccio'],
            ['role'=>'Regista associato','name'=>'Piero Di Blasio'],
            ['role'=>'Coreografo associato','name'=>'Simone Rossari'],
            ['role'=>'Art Director','name'=>'Matteo Bonacci'],
            ['role'=>'Scenografia','name'=>'Francesco Mari'],
            ['role'=>'Costumi','name'=>'Maria Sabato'],
            ['role'=>'Direzione tecnica','name'=>'Lucio Mazzoli'],
        ],
        'production'=>[
            ['role'=>'Produzione','name'=>'Nero Musical srl'],
            ['role'=>'Produzione esecutiva','name'=>'Retropalco Srl'],
        ],
    ],
];
