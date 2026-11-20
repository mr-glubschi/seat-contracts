<?php

return [
    // Item groups shown on the pages, by their name in SeAT's SDE (invGroups.groupName). All contracts are
    // collected with their contents anyway; this list only decides which ships get a price without fitting.
    // The status page lists any name that is not found in the SDE.
    'groups' => [
        'Carrier',
        'Command Carrier',
        'Dreadnought',
        'Lancer Dreadnought',
        'Force Auxiliary',
        'Supercarrier',
        'Titan',
        'Capital Industrial Ship',
        'Freighter',
        'Jump Freighter',
    ],

    // Jita IV - Moon 4 - Caldari Navy Assembly Plant: the fitting is valued at the highest buy order here.
    'jita_station_id' => 60003760,

    // Contracts whose contents one job reads, one request after the other. 50,000 contracts on the first run
    // become 500 jobs on SeAT's public queue.
    'items_per_job' => 100,

    // A contract whose contents were queued but not read after this many minutes is queued again (the job
    // failed for good, or the queue was flushed).
    'requeue_after_minutes' => 120,
];
