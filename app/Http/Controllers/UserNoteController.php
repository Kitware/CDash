<?php
namespace App\Http\Controllers;

use CDash\Model\BuildUserNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserNoteController extends AbstractBuildController
{
    public function apiAddUserNote(): JsonResponse
    {
        init_api_request();
        $response = array();

        if (!Auth::check()) {
            return;
        }

        $build = get_request_build();

        if (is_null($build)) {
            return;
        }

        if (!isset($_REQUEST['AddNote']) || !isset($_REQUEST['Status']) ||
            strlen($_REQUEST['AddNote']) < 1 ||  strlen($_REQUEST['Status']) < 1) {
            abort(400, 'No note specified');
        }

        // Add the note.
        $userNote = new BuildUserNote();
        $userNote->BuildId = $build->Id;
        $userNote->UserId = Auth::id();
        $userNote->Note = $_REQUEST['AddNote'];
        $userNote->Status = $_REQUEST['Status'];
        $userNote->TimeStamp = gmdate(FMT_DATETIME);

        if (!$userNote->Insert()) {
            abort(400, 'Error adding note');
        }

        $response['note'] = $userNote->marshal();
        echo json_encode(cast_data_for_JSON($response));
    }
}
