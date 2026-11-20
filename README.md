# Contracts for SeAT

A [SeAT 5](https://github.com/eveseat/seat) plugin that collects the contracts for capital ships in all regions
and shows what a hull costs: lowest price, median, average and the price over time, grouped by ship class and
hull, with every single contract one click away in the game.

> **Status: v0.3 (price history).** All public contracts with their contents; capital ships by class, standard
> and faction hulls, and hull, with lowest price, median, average and trend; per hull a price chart and its
> contracts, each with its contents and a button to open it in the game. Private and corporation contracts
> follow in 0.4.

Sister plugins: [Quick Sell](https://github.com/mr-glubschi/seat-quick-sell),
[Market Orders](https://github.com/mr-glubschi/seat-market-orders).

## What it does

- **Ships:** all contracts are collected with their contents. The pages show capital ships first, chosen by
  group name in SeAT's SDE (`invGroups`, config `contracts.groups`): Carrier, Command Carrier, Dreadnought,
  Lancer Dreadnought, Force Auxiliary, Supercarrier, Titan, Capital Industrial Ship, Freighter, Jump Freighter.
  More groups can be added later.
- **Contracts:** item exchange and auction contracts that hand over a capital ship (`is_included = true`):
  public contracts in **all regions**; private and corporation contracts that SeAT already reads for your
  characters and corporations follow in 0.4.
- **Four levels, each one opens the next:**
  1. **Ship class** (e.g. Dreadnought): open contracts and the lowest ship price.
  2. **Standard / faction hulls:** the class split by the hulls' meta group in the SDE (`invMetaTypes`:
     Storyline, Faction, Officer and Deadspace are *faction*), each with lowest price, median and average. A
     class median over Revelation and Vehement together would describe neither.
  3. **Hull** (e.g. Naglfar): the same figures, plus the change of the median against 7 and 30 days ago.
  4. **Opened hull:** a price chart and the list of its open contracts: ship price, price, fitting value,
     location (security, system, region), issuer, expiry, contents on demand, and a button **Open in game**.
     The list sorts by any column (remembered in the browser).
- **Filters:** security (high-sec, low-sec, null-sec), region, only contracts with a known location. They apply
  to all figures and to the history.
- **Fitting removed from the price:** every other item in the contract is valued at **Jita buy** (the highest
  buy order in Jita 4-4, what you get when you sell right away), and that value is subtracted from the price.
  **Rigs count as 0 ISK:** a rig fitted to an assembled ship is destroyed when it is taken out. ESI's public
  contract items do not say whether a rig is fitted or packed in the cargo, so every rig counts as fitted.
  Items with no market (blueprint copies, abyssal modules, …) count as 0 ISK too; such contracts are marked,
  since their ship price may be too high.
- **Statistics:** the median is the main figure, since single contracts for 1 ISK or for absurd prices would
  distort an average. A ship price is an *outlier*, listed and marked but not counted, when it is
  - below half or above twice the median of its hull over the history window (90 days, open and closed
    contracts, from 3 contracts on), or
  - below 10 % of the median of its standard or faction hulls, which also catches 1-ISK bait for rare hulls.

  Contracts with more than one capital ship, contracts that ask for items in return, and auctions without a
  buyout are listed but not counted.
- **Locations and issuers:** NPC stations come from the SDE. Upwell structures and issuer names are only known
  when SeAT has seen them, so after every update the plugin asks SeAT's own jobs for the missing ones of open
  capital contracts (`Universe\Names`, and `StructureBatch` with the token of the next character that has
  `esi-universe.read_structures.v1`). A structure none of your characters may dock at stays unknown; the page
  then shows its region only.
- **Expired contracts** are no longer listed, but stay in the plugin's tables for the price history.

## Price history

ESI has no price history for contracts, so the plugin builds it from the contracts it has seen. The price of a
contract cannot change, so a contract is stored once; it is open from its issue date until it was gone from the
region lists or expired. The price of a day is the median of the counted contracts open at the end of that day.
The chart of a hull shows the last 90 days (config `contracts.history_days`): median and lowest asking price,
and dots for contracts that were *probably sold*.

The chart shows **asking prices**. Whether a contract was bought cannot be seen for public contracts: ESI just
stops listing it, whether it was accepted, deleted or expired. A public contract that is gone at least an hour
before its expiry date is counted as *probably sold*. Private and corporation contracts have a real status
(`finished`, `date_completed`), so for them the sale is known (0.4).

**Before the first run:** the history does not start on the day of installation, since a contract is counted
from its issue date. But for the time before the first run only the contracts still open then are known; those
sold earlier were never seen. These days are shaded in the chart and a trend that reaches back to them is marked
with ≈: their prices tend to be too high. After 30 days of collection the trends are real.

## Data and storage

| Data | Source | Kept |
| --- | --- | --- |
| Public contracts | ESI `/contracts/public/{region_id}/` (public, no token, 1000 per page, cached 30 minutes), fetched by the plugin's own job for every region | plugin table, also after expiry |
| Contents of public contracts | ESI `/contracts/public/items/{contract_id}/` (cached 1 hour, `204` when expired or just accepted), for every item exchange and auction contract | plugin table; read once per contract, since contents never change |
| Private and corporation contracts with contents | SeAT's tables `contract_details`, `contract_items`, `character_contracts`, `corporation_contracts` (SeAT's jobs `Contracts\Character\*` and `Contracts\Corporation\*`) | read only |
| Jita buy prices for the fitting | SeAT's table `market_orders`, filled once a day by SeAT's job `esi:update:prices` with all orders of the market region (must be The Forge, SeAT's default); highest buy order placed in Jita 4-4 | stored with the contract when its contents are read, so its ship price does not change later |
| Ship names, groups, meta groups, rigs, regions, systems, stations, structures | SeAT's SDE (`invTypes`, `invGroups`, `invMetaTypes`, `dgmTypeEffects`, `regions`, `solar_systems`, `staStations`) and `universe_structures` | read only |
| Price chart | Chart.js 2, which SeAT already loads on every page | nothing |
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
- **Storage:** about 400,000 item rows for the open contracts. `contracts:prune` (daily at 04:23) keeps it from
  growing: for contracts closed more than `CONTRACTS_KEEP_ITEMS_DAYS` days ago (default 90, set it in SeAT's
  `.env`; 0 = never delete) it deletes the contents. Contracts with a capital ship keep their row with price
  and valuation for the history; contracts without one are deleted completely. A group added later can only
  be valued for contracts whose contents are still there.

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

**After an update** that changes how contracts are valued (0.3: rigs count as 0 ISK), or after adding groups to
`contracts.groups`, value the stored contracts again once:

```bash
docker compose exec front php artisan contracts:revalue
```

It uses today's Jita buy prices; the prices of the day the contents were read are not kept per item.

## Roadmap

| Version | Content |
| --- | --- |
| 0.1 | Skeleton: package, menu entry, permission, tables, job for all public contracts in all regions with their contents, ship price without fitting at Jita buy, status page |
| 0.2 | Overview by ship class and hull with lowest price, median and average, outliers, contract list with contents, open in game, names of issuers and structures |
| 0.3 | Price history: chart per hull, trend over 7 and 30 days, *probably sold* dots; standard and faction hulls; rigs at 0 ISK; outliers over 90 days with a floor; filters; sortable contract list loaded per hull; `contracts:revalue`, `contracts:prune` |
| 0.4 | Private and corporation contracts from SeAT's tables, with real sales |
| later | More groups on the pages (faction ships, blueprint copies by runs, ME and TE, any item), jumps from your location |

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
