<?php

namespace App\Http\Submission\Traits;

use App\Models\Site;
use App\Models\SiteInformation;

trait UpdatesSiteInformation
{
    /**
     * Saves a new site information record if $newInformation is different from the most recent
     * information recorded for the site.
     */
    protected function updateSiteInfoIfChanged(Site $site, SiteInformation $newInformation): void
    {
        if ($site->information()->count() === 0) {
            // No existing information, so save whatever we're given, regardless of whether it's all null.
            $site->information()->save($newInformation);
            return;
        }

        $all_attributes_null = true;
        foreach ($newInformation->getFillable() as $attribute) {
            $all_attributes_null = $all_attributes_null && $newInformation->getAttribute($attribute) === null;
        }

        if ($all_attributes_null) {
            // Don't save information which is all null unless this is the first information we've received.
            return;
        }

        foreach ($newInformation->getFillable() as $attribute) {
            if ($site->mostRecentInformation?->getAttribute($attribute) !== $newInformation->getAttribute($attribute)) {
                // Something changed, so we can go ahead and save the new information.
                $site->information()->save($newInformation);
                return;
            }
        }
    }
}
