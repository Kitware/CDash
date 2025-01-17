<?php

namespace App\Jobs;

use App\Mail\AuthTokenExpiring;
use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class NotifyExpiringAuthTokens implements ShouldQueue
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
        $tokens_expiring_7_days = AuthToken::with('user')
            ->where('expires', '<=', Carbon::now()->addDays(7))
            ->where('expires', '>', Carbon::now())
            ->get();

        foreach ($tokens_expiring_7_days as $token) {
            /** @var User $user */
            $user = $token->user;
            Mail::to($user)->send(new AuthTokenExpiring($token));
        }
    }
}
