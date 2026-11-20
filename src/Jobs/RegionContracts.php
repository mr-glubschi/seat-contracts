<?php

namespace Glubschi\Seat\Contracts\Jobs;

use Carbon\Carbon;
use Glubschi\Seat\Contracts\Contracts\PublicContracts;
use Seat\Eseye\Exceptions\RequestFailedException;
use Seat\Eveapi\Jobs\EsiBase;

/**
 * Reads all public contracts of one region and stores the item exchange and auction contracts.
 *
 * A SeAT ESI job (EsiBase): SeAT's own ESI client, rate limit and error handling, on the "public" queue. One
 * request per page of /contracts/public/{region_id}/ (1000 contracts per page, cached by ESI for 30 minutes).
 * Only when every page was read are the region's contracts that were not listed marked as gone, so a failed
 * request never makes open contracts look sold.
 */
class RegionContracts extends EsiBase
{
    protected $method = 'get';

    protected $endpoint = '/contracts/public/{region_id}/';

    protected string $compatibility_date = '2025-07-20';

    protected $tags = ['public', 'contracts', 'glubschi-contracts'];

    protected $page = 1;

    public function __construct(protected int $regionId)
    {
        parent::__construct();
    }

    public function tags(): array
    {
        return array_merge(parent::tags(), ['region:' . $this->regionId]);
    }

    public function regionId(): int
    {
        return $this->regionId;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // One time for the whole run: every listed contract gets it as last_seen_at, and everything older
        // in this region was not listed.
        $now = Carbon::now()->startOfSecond();

        try {
            while (true) {
                $response = $this->retrieve(['region_id' => $this->regionId]);
                $body = $response->getBody();
                if (is_array($body)) {
                    PublicContracts::store(PublicContracts::rows($body, $this->regionId, $now));
                }
                if (! $this->nextPage($response->getPagesCount())) {
                    break;
                }
            }
        } catch (RequestFailedException $e) {
            // The SDE knows regions where contracts cannot exist (e.g. the Abyss); ESI answers 404 for them.
            if ($e->getEsiResponse()->getErrorCode() === 404 && $this->page === 1) {
                return;
            }
            throw $e;
        }

        PublicContracts::markGone($this->regionId, $now, $now);
    }
}
