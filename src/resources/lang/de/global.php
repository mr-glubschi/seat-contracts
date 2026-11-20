<?php

return [
    'title' => 'Contracts',
    'description' => 'Preise von Capital-Schiffen aus Contracts in allen Regionen',

    'no_data' => 'Noch keine Contracts gelesen. SeAT liest sie alle 30 Minuten (Eintrag contracts:update im Zeitplan); der erste Durchlauf dauert eine Weile.',
    'no_jita' => 'SeAT hat keine Buy Orders für Jita 4-4, darum kann das Fitting nicht bewertet werden. SeAT liest sie einmal am Tag (esi:update:prices) für die Marktregion unter Einstellungen, und die muss The Forge sein.',

    'collection' => 'Sammlung',
    'last_seen' => 'Letzte Regionsliste gelesen',
    'open' => 'Offene Contracts (Item Exchange und Auktion)',
    'read' => 'Inhalt gelesen',
    'pending' => 'Inhalt noch zu lesen',
    'unavailable' => 'Inhalt nicht mehr verfügbar',
    'gone' => 'Verschwundene Contracts (für den Preisverlauf behalten)',
    'items' => 'Gespeicherte Item-Zeilen',

    'capitals' => 'Capital-Schiffe in offenen Contracts',
    'col_group' => 'Schiffsklasse',
    'col_open' => 'Contracts',
    'col_priced' => 'mit Schiffspreis',
    'col_lowest' => 'Tiefster Schiffspreis',
    'group_missing' => 'nicht in der SDE',
    'group_missing_help' => 'Die SDE von SeAT hat keine Gruppe mit diesem Namen; Config contracts.groups prüfen.',
    'capitals_note' => 'Schiffspreis: Preis (bei Auktionen der Sofortkaufpreis) minus der restliche Inhalt zu Jita Buy. Contracts mit mehr als einem Capital oder die Items im Gegenzug verlangen, haben keinen Schiffspreis.',
];
