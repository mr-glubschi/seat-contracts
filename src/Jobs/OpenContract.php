<?php

namespace Glubschi\Seat\Contracts\Jobs;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Opens the contract window of one contract in the EVE client of one character.
 *
 * ESI: POST /ui/openwindow/contract/?contract_id=…, scope esi-ui.open_window.v1 ("Open the contract window
 * inside the client"); answers 204 without a body. As with Market Orders' OpenMarketDetails, the character must
 * be logged in to the game, and nothing happens otherwise, without an error from ESI.
 *
 * Not queued: the controller calls handle() directly, so the window opens at once and is not held back by SeAT's
 * queue middleware while a regular update of the same character runs. The controller checks the scope itself.
 */
class OpenContract extends AbstractAuthCharacterJob
{
    public const SCOPE = 'esi-ui.open_window.v1';

    protected $method = 'post';

    protected $endpoint = '/ui/openwindow/contract/';

    protected string $compatibility_date = '2025-07-20';

    protected $scope = self::SCOPE;

    protected $tags = ['character', 'ui', 'glubschi-contracts'];

    public function __construct(RefreshToken $token, protected int $contractId)
    {
        parent::__construct($token);
        $this->query_string = ['contract_id' => $contractId];
    }

    /**
     * Sends the request. Does not call parent::handle(): that only logs the queue job ID, which does not exist
     * when the job is run directly.
     *
     * @throws \Throwable  SeAT's ESI exceptions; the controller turns them into a message for the page
     */
    public function handle(): void
    {
        $this->retrieve();
    }

    public function contractId(): int
    {
        return $this->contractId;
    }
}
