<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

abstract class Controller
{
    /**
     * The page for a link that has expired, been cancelled or already been used.
     */
    protected function unavailable(string $what): Response
    {
        return response()->view('groups.link-unavailable', ['what' => $what], 410);
    }
}
