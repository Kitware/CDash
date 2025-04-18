<?php

namespace App\Jobs;

use App\Mail\AuthTokenExpired;
use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Removes expired auth tokens.
 */
class PruneAuthTokens implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $expired_tokens = AuthToken::expired()->get();
        foreach ($expired_tokens as $token) {
            /** @var User $user */
            $user = $token->user;
            Mail::to($user)->send(new AuthTokenExpired($token));
        }

        AuthToken::expired()->delete();
    }
}
