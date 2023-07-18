<?php
namespace App\Http\Controllers;

use CDash\Model\BuildUserNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class UserNoteController extends AbstractBuildController
{
    public function apiAddUserNote(): JsonResponse
    {
        if (!is_numeric(request()->post('buildid'))) {
            abort(400, 'Invalid buildid!');
        }
        $this->setBuildById((int) request()->post('buildid'));

        if (request()->post('AddNote') === null || request()->post('Status') === null ||
            strlen(request()->post('AddNote')) < 1 || strlen(request()->post('Status')) < 1) {
            abort(400, 'No note specified');
        }

        // Add the note.
        $userNote = new BuildUserNote();
        $userNote->BuildId = $this->build->Id;
        $userNote->UserId = Auth::id();
        $userNote->Note = request()->post('AddNote');
        $userNote->Status = request()->post('Status');
        $userNote->TimeStamp = gmdate(FMT_DATETIME);

        if (!$userNote->Insert()) {
            abort(400, 'Error adding note');
        }

        $response = [];
        $response['note'] = $userNote->marshal();
        return response()->json(cast_data_for_JSON($response));
    }
}
