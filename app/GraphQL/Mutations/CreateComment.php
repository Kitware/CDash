<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Build;
use App\Models\Comment;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;

final class CreateComment extends AbstractMutation
{
    public ?Comment $comment = null;

    /**
     * @param array{
     *     buildId: string,
     *     text: string,
     * } $args
     *
     * @throws AuthenticationException
     */
    public function __invoke(null $_, array $args): self
    {
        $user = auth()->user();
        if ($user === null) {
            throw new AuthenticationException('This action is unauthorized.');
        }

        $build = Build::find((int) $args['buildId']);

        Gate::authorize('view', $build);

        /** @var Comment $comment */
        $comment = $build->comments()->create([
            'userid' => $user->id,
            'text' => $args['text'],
            'status' => Comment::STATUS_NORMAL,
        ]);

        $this->comment = $comment->refresh();

        return $this;
    }
}
