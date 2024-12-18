<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

class EmailSentListener
{
    public function handle(MessageSent $event): void
    {
        $addresses = [];
        foreach ($event->message->getTo() as $address) {
            $addresses[] = $address->getAddress();
        }
        $addresses = implode(', ', $addresses);

        // TODO: Clean up this logging code by using Log facade with multiple message levels
        if (config('app.debug')) {
            add_log($addresses, 'TESTING: EMAIL', LOG_DEBUG);
            add_log($event->message->getSubject(), 'TESTING: EMAILTITLE', LOG_DEBUG);
            add_log($event->message->getTextBody(), 'TESTING: EMAILBODY', LOG_DEBUG);
        } else {
            Log::info("Sent email titled '{$event->message->getSubject()}' to {$addresses}");
        }
    }
}
