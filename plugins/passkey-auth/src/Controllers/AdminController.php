<?php

namespace PasskeyAuth\Controllers;

use PasskeyAuth\Models\Passkey;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;

class AdminController extends Controller
{
    public function index()
    {
        $passkeys = Passkey::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('PasskeyAuth::admin.index', [
            'passkeys' => $passkeys,
            'links' => $passkeys->links()
        ]);
    }

    public function rename(Request $request, $id)
    {
        try {
            $passkey = Passkey::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255'
            ]);

            $passkey->name = $request->input('name');
            $passkey->save();

            return response()->json(['message' => trans('PasskeyAuth::front-end.admin.rename.success')]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => trans('PasskeyAuth::general.errors.not_found')], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => trans('PasskeyAuth::general.errors.rename_failed')], 500);
        }
    }

    public function delete($id)
    {
        try {
            $passkey = Passkey::findOrFail($id);
            $passkey->delete();

            return response()->json(['message' => trans('PasskeyAuth::front-end.admin.delete.success')]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => trans('PasskeyAuth::general.errors.not_found')], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => trans('PasskeyAuth::general.errors.delete_failed')], 500);
        }
    }
}