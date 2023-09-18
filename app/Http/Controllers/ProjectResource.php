<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

final class ProjectResource extends Controller
{
    /**
     * Return a listing of all projects available to the current user (if applicable)
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'projects' => Project::forUser()->orderBy('id')->get(),
        ]);
    }

    /**
     * POST a new project
     */
    public function store(Request $request): JsonResponse
    {
        abort(Response::HTTP_NOT_IMPLEMENTED, 'Method not implemented');
    }

    /**
     * GET the specified project
     *
     * Project access control happens prior to binding the parameter, so we can treat anything
     * accessible from this function as safe.
     */
    public function show(Project $project): JsonResponse
    {
        return response()->json([
            'project' => $project,
        ]);
    }

    /**
     * PUT the specified project
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        abort(Response::HTTP_NOT_IMPLEMENTED, 'Method not implemented');
    }

    /**
     * DELETE the specified project
     */
    public function destroy(Project $project): JsonResponse
    {
        abort(Response::HTTP_NOT_IMPLEMENTED, 'Method not implemented');
    }
}
