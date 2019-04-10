<?php
use CDash\Messaging\Subscription\Subscription;
use CDash\Config;
$descriptions = $subscription->getTopicDescriptions(CASE_LOWER);
$summary = $subscription->getBuildSummary();
$config = Config::getInstance();
?>
A submission to CDash for the project {{ $subscription->getProjectName() }} has {{ implode(', ', $descriptions ) }}. You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.

Details on the submission can be found at {{ $summary['project_url'] }}.

Project: {{ $summary['project_name'] }}
@if(count($summary['build_subproject_names']) === 1)
SubProject: {{ $summary['build_subproject_names'][0] }}
@endif
Site: {{ $summary['site_name'] }}
Build Name: {{ $summary['build_name'] }}
Build Time: {{ $summary['build_time'] }}
Type: {{ $summary['build_type'] }}
@foreach($subscription->getTopicCollection() as $topic)
Total {{ $topic->getTopicDescription() }}: {{ $topic->getTopicCount() }}
@endforeach

@foreach($subscription->getTopicCollection() as $type => $topic)
<?php
$collection = $topic->getTopicCollection();
$size = Subscription::getMaxDisplayItems();
$items = $collection->first($size);
$warning = $size < $collection->count() ? "(first {$size} included)" : '';
$project = $subscription->getProject();
?>
*{{ $topic->getTopicDescription() }}* {{ $warning }}

@foreach($descriptions as $description)
<?php $reference = str_replace(' ', '_', $description); ?>
@include("issue.{$reference}", ['items' => $items, 'maxChars' => $project->EmailMaxChars])
@endforeach
@endforeach

-CDash on {{ $config->getServer() }}

