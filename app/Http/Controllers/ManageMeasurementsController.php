<?php

namespace App\Http\Controllers;

use App\Models\Measurement;
use App\Models\Project as EloquentProject;
use App\Services\PageTimer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class ManageMeasurementsController extends AbstractProjectController
{
    // Render the 'manage measurements' page.
    public function show($project_id)
    {
        $this->setProjectById((int) $project_id);
        Gate::authorize('edit-project', $this->project);

        return $this->view('admin.measurements', 'Test Measurements');
    }

    public function apiGet(): JsonResponse
    {
        $this->setProjectById((int) request()->query('projectid'));
        Gate::authorize('edit-project', $this->project);

        $pageTimer = new PageTimer();
        $response = begin_JSON_response();

        get_dashboard_JSON($this->project->GetName(), null, $response);
        $response['title'] = "{$this->project->Name} Test Measurements";

        // Menu
        $menu_response = [];
        $menu_response['back'] = '/user';
        $response['menu'] = $menu_response;
        $response['hidenav'] = true;

        // Get any measurements associated with this project's tests.
        $measurements_response = [];
        $measurements = EloquentProject::findOrFail($this->project->Id)
            ->measurements()
            ->orderBy('position', 'asc')
            ->get();

        foreach ($measurements as $measurement) {
            $measurements_response[] = [
                'id' => $measurement->id,
                'name' => $measurement->name,
                'position' => $measurement->position,
            ];
        }
        $response['measurements'] = $measurements_response;
        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    public function apiPost(): JsonResponse
    {
        $this->setProjectById((int) request()->input('projectid'));
        Gate::authorize('edit-project', $this->project);

        if (!request()->filled('measurements') || !is_array(request()->input('measurements'))) {
            abort(400, 'A "measurements" parameter must be specified.');
        }

        $OK = true;
        $new_ID = null;
        foreach (request()->input('measurements') as $measurement_data) {
            $id = (int) $measurement_data['id'];
            if ($id > 0) {
                // Update an existing measurement rather than creating a new one.
                $measurement = Measurement::find($id);
            } else {
                $measurement = new Measurement();
            }
            $measurement->projectid = $this->project->Id;
            $measurement->name = $measurement_data['name'];
            $measurement->position = $measurement_data['position'];
            if (!$measurement->save()) {
                $OK = false;
            } elseif ($id < 1) {
                // Report the ID of the newly created measurement (if any).
                $new_ID = $measurement->id;
            }
        }

        if (!$OK) {
            abort(500);
        }

        if ($new_ID) {
            $response = ['id' => $new_ID];
            return response()->json($response);
        }
        return response()->json();
    }

    public function apiDelete(): JsonResponse
    {
        $this->setProjectById((int) request()->input('projectid'));
        Gate::authorize('edit-project', $this->project);

        if (!request()->filled('id')) {
            abort(400, 'Invalid measurement ID provided.');
        }

        $deleted = EloquentProject::findOrFail($this->project->Id)
            ->measurements()
            ->where('id', (int) request()->input('id'))
            ->delete();

        if ($deleted) {
            return response()->json();
        } else {
            return response()->json([], 404);
        }
    }
}
