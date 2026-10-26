<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ProjectLogoController extends AbstractController
{
    /**
     * @throws HttpException
     * @throws UnableToRetrieveMetadata
     */
    public function __invoke(Request $request, int $project_id): StreamedResponse
    {
        $project = Project::find($project_id);

        // Return 404 instead of 403 to avoid revealing which private projects exist.
        if ($project === null || Gate::denies('view', $project)) {
            abort(404);
        }

        $logo_path = $project->logo_path;
        if ($logo_path === null || Storage::missing($logo_path)) {
            abort(404);
        }

        // Logo URLs include a hash of the logo, so only requests for the current hash can be cached indefinitely.
        $is_current_version = $request->query('v') === pathinfo($logo_path, PATHINFO_FILENAME);

        return Storage::response($logo_path, null, [
            'Cache-Control' => $is_current_version ? 'private, max-age=31536000, immutable' : 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
