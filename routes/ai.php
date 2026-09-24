<?php

use App\Mcp\Servers\StradenServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', StradenServer::class)
    ->middleware(['auth:sanctum', 'throttle:mcp'])
    ->name('mcp');
