# Contracts for SeAT

A [SeAT 5](https://github.com/eveseat/seat) plugin that collects the contracts for capital ships in all regions
and shows what a hull costs: lowest price, median, average and the price over time, grouped by ship class and
hull, with every single contract one click away in the game.

> **Status: v0.2 (overview).** All public contracts with their contents; capital ships by class and hull with
> lowest price, median and average, every contract with its contents and a button to open it in the game. The
> price history follows in 0.3.

Sister plugins: [Quick Sell](https://github.com/mr-glubschi/seat-quick-sell),
[Market Orders](https://github.com/mr-glubschi/seat-market-orders).

## What it does

- **Ships:** all contracts are collected with their contents. The pages show capital ships first, chosen by
  group name in SeAT's SDE (`invGroups`, config `contracts.groups`): Carrier, Command Carrier, Dreadnought,
  Lancer Dreadnought, Force Auxiliary, Supercarrier, Titan, Capital Industrial Ship, Freighter, Jump Freighter.
  More groups can be added later.
- **Contracts:** item exchange and auction contracts that hand over a capital ship (`is_included = true`):
  - public contracts in **all regions**,
  - private and corporation contracts that SeAT already reads for your characters and corporations.
- **Three levels, each one opens the next:**
  1. **Ship class** (e.g. Dreadnought): open contracts, lowest price, median, trend over 7 and 30 days.
  2. **Hull** (e.g. Naglfar): the same figures, plus a chart of the price over time.
  3. **Contract:** price, ship price without fitting, location (region, system, security), availability
     (public / corporation / private), issued and expires, contents, and a button **Open in game**.
- **Fitting removed from the price:** every other item in the contract is valued at **Jita buy** (the highest
  buy order in Jita 4-4, what you get when you sell right away), and that value is subtracted from the price.
  Items with no market (blueprint copies, abyssal modules, …) count as 0 ISK; such contracts are marked,
  since their ship price may be too high.
- **Statistics:** the median is the main figure, since single contracts for 1 ISK or for absurd prices would
  distort an average. A ship price below half or above twice the median of its hull is an *outlier* (from 3
  contracts of that hull on): left out of the figures, but listed and marked. Contracts with more than one
  capital ship, contracts that ask for items in return, and auctions without a buyout are listed but not
  counted. The class figures are taken over the counted contracts of all its hulls, so a class with expensive
  faction hulls has a higher average than its standard hulls.
- **Locations and issuers:** NPC stations come from the SDE. Upwell structures and issuer names are only known
  when SeAT has seen them, so after every update the plugin asks SeAT's own jobs for the missing ones of open
  capital contracts (`Universe\Names`, and `StructureBatch` with the token of the next character that has
  `esi-universe.read_structures.v1`). A structure none of your characters may dock at stays unknown; the page
  then shows its region only.
- **Expired contracts** are no longer shown, but stay in the plugin's tables for the price history.

## Price history

ESI has no price history for contracts, so the plugin builds it from the contracts it has seen. It starts
on the day the plugin is installed. The price of a contract cannot change, so a contract is stored once,
with the day it was first seen and the day it was gone. The price for a day is the median of the contracts
open on that day.

The chart shows **asking prices**. Whether a contract was bought cannot be seen for public contracts: ESI
just stops listing it, whether it was accepted, deleted or expired. A public contract that is gone before
its expiry date is counted as *probably sold*, and the chart shows these as a second, estimated line.
Private and corporation contracts have a real status (`finished`, `date_completed`), so for them the sale
is known.

## Data and storage

| Data | Source | Kept |
| --- | --- | --- |
| Public contracts | ESI `/contracts/public/{region_id}/` (public, no token, 1000 per page, cached 30 minutes), fetched by the plugin's own job for every region | plugin table, also after expiry |
| Contents of public contracts | ESI `/contracts/public/items/{contract_id}/` (cached 1 hour, `204` when expired or just accepted), for every item exchange and auction contract | plugin table; read once per contract, since contents never change |
| Private and corporation contracts with contents | SeAT's tables `contract_details`, `contract_items`, `character_contracts`, `corporation_contracts` (SeAT's jobs `Contracts\Character\*` and `Contracts\Corporation\*`) | read only |
| Jita buy prices for the fitting | SeAT's table `market_orders`, filled once a day by SeAT's job `esi:update:prices` with all orders of the market region (must be The Forge, SeAT's default); highest buy order placed in Jita 4-4 | stored with the contract when its contents are read, so its ship price does not change later |
| Ship names, groups, regions, systems, stations, structures | SeAT's SDE (`invTypes`, `invGroups`, `regions`, `mapSolarSystems`, `staStations`) and `universe_structures` | read only |
| Open contract in game | ESI `POST /ui/openwindow/contract/`, only on your click | nothing |

**All contracts with contents:** ESI cannot filter public contracts by item; the region list has no contents.
So the plugin reads the contents of **every** item exchange and auction contract once, not only those with
capitals. Other ships and items can then be added later without fetching anything again, and their history
starts on the day the plugin was installed. Blueprint copies keep their runs, ME and TE.

Measured on 2026-11-20: 51,119 public contracts in 114 regions (156 pages, 35 of them in The Forge), of which
49,803 item exchange, 650 auction and 666 courier. A sample of 300 contracts had 8 items on average
(median 1, at most 348), each on one page. So:

- **First run:** about 50,000 item requests, once. At 10 requests per second that is under 1.5 hours.
- **Later runs:** 156 list requests every 30 minutes, plus one item request per new contract.
- **Storage:** about 400,000 item rows for the open contracts, growing with every new contract. Expired
  contracts and their items are kept for the history. A later version adds a setting to delete the items of
  contracts that expired more than a chosen time ago, while their prices stay.

Couriers and loans are not stored: they have no price for an item.

**How a run works:** the command `contracts:update` queues one job per region of SeAT's SDE (table `regions`)
as a batch on SeAT's `public` queue. A region is marked as read only when all its pages were read; then its
contracts that were not listed get their *gone* time. When the batch is done, the contents of new contracts are
queued in jobs of 100 contracts (config `contracts.items_per_job`). A contract is queued once; if its contents
are still not read two hours later (config `contracts.requeue_after_minutes`), it is queued again.

**Private and corporation contracts:** ESI returns a contract to a character or corporation only if it is the
issuer, acceptor or assignee, and only for 30 days (or while `in_progress`). It needs the scopes
`esi-contracts.read_character_contracts.v1` and `esi-contracts.read_corporation_contracts.v1`.

**Alliance contracts are not available.** ESI has no endpoint for them, and a contract assigned to an alliance
is not returned to its members. One shows up only if one of your characters or corporations issued or
accepted it.

**Who sees what:** every SeAT user sees all public contracts, and only the private and corporation contracts
that one of their own characters or corporations can see in SeAT. All of them count for the price figures,
as single prices without details.

**Open in game** needs the scope `esi-ui.open_window.v1` (part of SeAT's default login scopes) and a character
who is logged in to the game, as in Market Orders. The contract opens for the character you choose.

## Install

Contracts is installed like any other SeAT plugin (see SeAT's
[community packages guide](https://github.com/eveseat/docs/blob/master/docs/community_packages.md)):

1. Add `mr-glubschi/seat-contracts` to `SEAT_PLUGINS` in your SeAT `.env`, comma-separated with other plugins.
2. Restart the SeAT stack. On start, SeAT installs the plugin and its tables, and adds the job to SeAT's
   schedule (every 30 minutes, as long as ESI caches the region lists).
3. In SeAT, give the permission **View Contracts** (`contracts.view`) to a role, under
   *Settings → Access Management*. Administrators have it automatically.

The first run reads the contents of all public contracts once (about 50,000 requests) and takes a while;
later runs only read new ones. To start a run right away instead of waiting for the schedule:

```bash
docker compose exec front php artisan contracts:update
```

## Roadmap

| Version | Content |
| --- | --- |
| 0.1 | Skeleton: package, menu entry, permission, tables, job for all public contracts in all regions with their contents, ship price without fitting at Jita buy, status page |
| 0.2 | Overview by ship class and hull with lowest price, median and average, outliers, contract list with contents, open in game, names of issuers and structures |
| 0.3 | Price history: chart per hull, trend over 7 and 30 days, *probably sold* line |
| 0.4 | Private and corporation contracts from SeAT's tables, with real sales |
| later | Setting to delete the items of long expired contracts, more groups on the pages (faction ships, blueprint copies by runs, ME and TE, any item), jumps from your location |

## Development

```bash
composer install
vendor/bin/phpunit
```

The tests boot the plugin with [Orchestra Testbench](https://github.com/orchestral/testbench) without
the rest of SeAT. To try the plugin in a running SeAT, follow SeAT's
[developer installation](https://github.com/eveseat/docs/blob/master/docs/developer_guides/developer_installation.md):
clone this repository into the `packages` folder of your SeAT stack and register it in
`packages/override.json`:

```json
{
  "autoload": { "Glubschi\\Seat\\Contracts\\": "packages/mr-glubschi/seat-contracts/src/" },
  "providers": [ "Glubschi\\Seat\\Contracts\\ContractsServiceProvider" ]
}
```

## License

GPL-2.0-or-later, like SeAT.
