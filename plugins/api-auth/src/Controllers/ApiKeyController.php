<?php

namespace ApiAuth\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ApiAuth\Models\ApiKey;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ApiKeyController extends Controller
{
    public function index()
    {
        $keys = ApiKey::orderBy('created_at', 'desc')->get();
        return view('ApiAuth::keys', compact('keys'));
    }

    public function generate(Request $request)
    {
        try {
            Log::info('Generating API key', ['request' => $request->all()]);

            $request->validate([
                'name' => 'required|string|max:255',
                'expires_at' => 'nullable|date|after:now'
            ]);

            $expiresAt = $request->input('expires_at') ? Carbon::parse($request->input('expires_at')) : null;

            $key = ApiKey::create([
                'name' => $request->input('name'),
                'key' => 'sk-' . Str::random(32),
                'expires_at' => $expiresAt
            ]);

            Log::info('API key generated successfully', ['key' => $key->id]);

            return response()->json([
                'message' => '密钥创建成功',
                'data' => $key
            ], 200);
        } catch (ValidationException $e) {
            Log::error('API key validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'message' => '验证失败',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('API key generation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => '创建密钥失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $key = ApiKey::findOrFail($id);
            $key->delete();
            Log::info('API key deleted', ['id' => $id]);

            return response()->json([
                'message' => '密钥删除成功'
            ], 200);
        } catch (\Exception $e) {
            Log::error('API key deletion failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => '删除密钥失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
