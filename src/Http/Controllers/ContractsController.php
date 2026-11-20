<?php

namespace Glubschi\Seat\Contracts\Http\Controllers;

use Glubschi\Seat\Contracts\Contracts\Status;
use Seat\Web\Http\Controllers\Controller;

/**
 * The Contracts page. In v0.1 it shows how far the collection is; the tables by ship class and hull follow in 0.2.
 */
class ContractsController extends Controller
{
    public function index()
    {
        return view('contracts::index', ['status' => Status::figures()]);
    }
}
