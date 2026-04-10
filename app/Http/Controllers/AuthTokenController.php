<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuthToken;
use App\Utils\AuthTokenUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class AuthTokenController extends AbstractController
{
    public function manage(): View
    {
        return $this->vue('manage-auth-tokens', 'Authentication Tokens');
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
            $projectid = (int) $request->input('projectid');
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
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return response()->json($gen_auth_token);
    }
}
