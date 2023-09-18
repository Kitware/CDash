<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

final class ProjectAdministratorResource extends Controller
{
    /**
     * Return a listing of the users who have administrative access to this project.
     * Note: This does not include CDash-wide administrators who can administer any project,
     * unless the administrator has specifically been added as a project administrator as well.
     */
    public function index(Project $project): JsonResponse
    {
        return response()->json([
            'userids' => $project->administrators()->orderBy('id')->pluck('id'),
        ]);
    }

    /**
     * POST
     */
    public function store(Request $request): JsonResponse
    {
        abort(Response::HTTP_NOT_IMPLEMENTED, 'Method not implemented');
    }

    /**
     * GET
     */
    public function show(Project $project, User $user): JsonResponse
    {
        abort(Response::HTTP_NOT_IMPLEMENTED, 'Method not implemented');
    }

    /**
     * PUT
     */
    public function update(Request $request, Project $project, User $user): JsonResponse
    {
        abort(Response::HTTP_NOT_IMPLEMENTED, 'Method not implemented');
    }

    /**
     * DELETE
     */
    public function destroy(Project $project, User $user): JsonResponse
    {
        abort(Response::HTTP_NOT_IMPLEMENTED, 'Method not implemented');
    }
}
