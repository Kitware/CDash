<?php
namespace App\Http\Controllers;

use App\Models\Comment;
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

        if (request()->post('Status') < 0 || request()->post('Status') > 2) {
            abort(400, 'Invalid status');
        }

        $eloquent_build = \App\Models\Build::findOrFail((int) $this->build->Id);

        /**
         * @var Comment $comment
         */
        $comment = $eloquent_build->comments()->create([
            'userid' => Auth::id(),
            'text' => request()->post('AddNote'),
            'status' => request()->post('Status'),
        ])->refresh();

        $response = [];
        $response['note'] = [
            'user' => $comment->user?->full_name,
            'date' => $comment->timestamp->toString(),
            'status' => match ($comment->status) {
                Comment::STATUS_NORMAL => '[note]',
                Comment::STATUS_FIX_IN_PROGRESS => '[fix in progress]',
                Comment::STATUS_FIXED => '[fixed]',
                default => '[unknown]',
            },
            'text' => $comment->text,
        ];
        return response()->json(cast_data_for_JSON($response));
    }
}
