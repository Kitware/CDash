<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuthToken;
use App\Utils\AuthTokenUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

final class AuthTokenController extends AbstractController
{
    public function manage(): View
    {
        return view('admin.manage-authtokens');
    }

    /**
     * Get all of the authentication tokens available across the entire system.
     * This method is only available to administrators.
     */
    public function fetchAll(): JsonResponse
    {
        $token_array = AuthTokenUtil::getAllTokens();
        $token_map = [];
        foreach ($token_array as $token) {
            $token_map[$token['hash']] = $token;
        }

        return response()->json([
            'tokens' => $token_map,
        ]);
    }

    public function createToken(Request $request): JsonResponse
    {
        $fields = ['scope', 'description'];
        foreach ($fields as $f) {
            if (!$request->has($f)) {
                return response()->json(['error' => "Missing field '{$f}'"], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($request->input('scope') !== AuthToken::SCOPE_FULL_ACCESS) {
            $projectid = intval($request->input('projectid'));
            if (!is_numeric($projectid)) {
                return response()->json(['error' => 'Invalid projectid'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            $projectid = -1;
        }

        try {
            $gen_auth_token = AuthTokenUtil::generateToken(
                Auth::id(),
                $projectid,
                $request->input('scope'),
                $request->input('description'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return response()->json($gen_auth_token);
    }

    public function deleteToken(string $token_hash): JsonResponse
    {
        if (!AuthTokenUtil::deleteToken($token_hash, Auth::id())) {
            return response()->json(['error' => 'Permissions error'], status: Response::HTTP_FORBIDDEN);
        }
        return response()->json('Token deleted');
    }
}
