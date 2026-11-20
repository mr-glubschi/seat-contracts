<?php

// Registered with scope "contracts" in ContractsServiceProvider, so "view" becomes contracts.view.
// Labels and descriptions must be translation keys (SeAT package development guide, "Permissions").
return [
    'view' => [
        'label' => 'contracts::permissions.view_label',
        'description' => 'contracts::permissions.view_description',
        'division' => 'financial',
    ],
];
