<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Laravel 12 leaves this off by default. CLAUDE.md § 5 requires an
    // authorize() call next to every model lookup, so every controller needs it.
    use AuthorizesRequests;
}
