<?php

// Sidebar entry (format documented in SeAT's package development guide, "Sidebar menu").
// One top-level link without sub-entries, like the sister plugins.
return [
    'contracts' => [
        'name' => 'contracts',
        'label' => 'contracts::menu.main',
        'icon' => 'fas fa-file-signature',
        'route_segment' => 'contracts',
        'route' => 'seatcontracts::home',
        'permission' => 'contracts.view',
    ],
];
