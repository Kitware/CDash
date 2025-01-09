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

        $context = [];
        if (config('app.debug')) {
            $context['subject'] = $event->message->getSubject();
            $context['body'] = $event->message->getTextBody();
        }

        Log::info("Sent email titled '{$event->message->getSubject()}' to {$addresses}", $context);
    }
}
