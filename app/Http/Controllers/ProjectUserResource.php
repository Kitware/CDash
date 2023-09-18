<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

final class ProjectUserResource extends Controller
{
    /**
     * Return a listing of all users who have been added to this project.
     * Note: This is *not* a listing of all users who can see the project. (e.g., other users who can
     * see a public project, but are not specifically added as members are not listed here)
     */
    public function index(Project $project): JsonResponse
    {
        $pivot_columns = [
            'role',
            'emailtype',
            'emailcategory',
            'emailsuccess',
            'emailmissingsites',
        ];

        $users = $project->users()->withPivot($pivot_columns)->orderBy('id')->get();

        $response = [];
        foreach ($users as $user) {
            $response[] = [
                'id' => (int) $user->id,
                'role' => (int) $user->pivot->role,
                'emailtype' => (int) $user->pivot->emailtype,
                'emailcategory' => (int) $user->pivot->emailcategory,
                'emailsuccess' => (bool) $user->pivot->emailsuccess,
                'emailmissingsites' => (bool) $user->pivot->emailmissingsites,
            ];
        }

        return response()->json([
            'users' => $response,
        ]);
    }

    /**
     * POST: Add the specified user to this project
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        abort(Response::HTTP_NOT_IMPLEMENTED, 'Method not implemented');
    }

    /**
     * GET the relationship between the specified project and specified user.
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
     * Remove the specified user from this project.
     */
    public function destroy(Project $project, User $user): JsonResponse
    {
        abort(Response::HTTP_NOT_IMPLEMENTED, 'Method not implemented');
    }
}
