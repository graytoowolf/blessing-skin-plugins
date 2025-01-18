<?php

namespace ApiAuth\Middleware;

use Closure;
use ApiAuth\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json(['message' => '缺少 API 密钥'], 401);
        }

        try {
            // 验证密钥并更新最后使用时间
            ApiKey::findAndValidate($apiKey);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'API 密钥无效'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return $next($request);
    }
}