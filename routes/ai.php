<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Mcp\Servers\ApiServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| C 端 API 能力的 MCP Server，客户端通过 Sanctum 个人访问令牌
| （Authorization: Bearer <token>）认证后调用。
|
*/
Mcp::web('mcp/v1', ApiServer::class)->middleware('auth:sanctum');
