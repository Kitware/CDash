<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$
  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.
  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

namespace App\Listeners;

use App\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class ConfiguredSendEmailVerificationNotification
{
    public function handle(Registered $event)
    {
        /** @var User|MustVerifyEmail $user */
        $user = $event->user;

        if (!($user instanceof MustVerifyEmail)) {
            return;
        }

        if (config('cdash.registration.email.verify')) {
            if (! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();
            }
        } else {
            $user->markEmailAsVerified();
        }
    }
}
