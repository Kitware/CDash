<?php
$config = \CDash\Config::getInstance();
$summary = $subscription->getBuildSummary();
$collection = $subscription->getTopicCollection();
$fixed = $collection->get('FixedTopic');
$descriptions = $subscription->getTopicDescriptions(CASE_LOWER);
$fixes = $summary['fixes'];

if (isset($fixes['UpdateError'])) {
    $fixes = $fixes['UpdateError'];
    $descriptions = [];
    if (isset($fixes['BuildError']['fixed']) && $fixes['BuildError']['fixed'] > 0) {
        $descriptions[] = 'errors';
    }

    if (isset($fixes['BuildWarning']['fixed']) && $fixes['BuildWarning']['fixed'] > 0) {
        $descriptions[] = 'warnings';
    }

    if (isset($fixes['TestFailure']['failed']['fixed']) && $fixes['TestFailure']['failed']['fixed'] > 0) {
        $descriptions[] = 'failing tests';
    }

    if (isset($fixes['TestFailure']['missing']['fixed']) && $fixes['TestFailure']['missing']['fixed'] > 0) {
        $descriptions[] = 'missing tests';
    }
}

?>
Congratulations. A submission to CDash for the project {{ $summary['project_name'] }} has fixed {{ implode(', ', $descriptions ) }}. You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.

Details on the submission can be found at {{ $summary['build_summary_url'] }}.

Project: {{ $summary['project_name'] }}
Site: {{ $summary['site_name'] }}
Build Name: {{ $summary['build_name'] }}
Build Time: {{ $summary['build_time'] }}
Type: {{ $summary['build_type'] }}
@if(isset($fixes['BuildError']['fixed']) && $fixes['BuildError']['fixed'] > 0)
Errors fixed: {{ $fixes['BuildErrors']['fixed'] }}
@endif
@if(isset($fixes['BuildWarning']['fixed']) && $fixes['BuildWarning']['fixed'] > 0)
Warnings fixed: {{ $fixes['BuildWarning']['fixed'] }}
@endif
@if(isset($fixes['TestFailure']['failed']['fixed'])
    && $fixes['TestFailure']['failed']['fixed'] > 0)
Test failures fixed: {{ $fixes['TestFailure']['failed']['fixed'] }}
@endif
@if(isset($fixes['TestFailure']['notrun']['fixed'])
    && $fixes['TestFailure']['notrun']['fixed'] > 0)
Tests not run fixed: {{ $fixes['TestFailure']['notrun']['fixed'] }}
@endif

-CDash on {{ $config->getServer() }}

