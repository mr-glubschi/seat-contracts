<?php

return [
    'title' => 'Contracts',
    'description' => 'Prices of capital ships from contracts in all regions',

    'no_data' => 'No contracts read yet. SeAT reads them every 30 minutes (schedule entry contracts:update); the first run takes a while.',
    'no_jita' => 'SeAT has no buy orders for Jita 4-4, so fittings cannot be valued. SeAT reads them once a day (esi:update:prices) for the market region under Settings, which must be The Forge.',

    'collection' => 'Collection',
    'last_seen' => 'Last region list read',
    'open' => 'Open contracts (item exchange and auction)',
    'read' => 'contents read',
    'pending' => 'contents still to read',
    'unavailable' => 'contents no longer available',
    'gone' => 'Contracts gone (kept for the price history)',
    'items' => 'Item rows stored',

    'capitals' => 'Capital ships in open contracts',
    'col_group' => 'Ship class',
    'col_open' => 'Contracts',
    'col_priced' => 'with ship price',
    'col_lowest' => 'Lowest ship price',
    'group_missing' => 'not in SDE',
    'group_missing_help' => 'SeAT\'s SDE has no group with this name; check config contracts.groups.',
    'capitals_note' => 'Ship price: price (buyout for auctions) minus the rest of the contents at Jita buy. Contracts with more than one capital, or that ask for items in return, have no ship price.',
];
