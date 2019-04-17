<?php
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Subscription\Subscription;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Config;
$descriptions = $subscription->getTopicDescriptions(CASE_LOWER);
$summary = $subscription->getBuildSummary();
$config = Config::getInstance();
$last = array_pop($descriptions);
$description = implode(', ', $descriptions);
/** @var NotificationPreferences $preferences */
$preferences = $subscription
    ->getSubscriber()
    ->getNotificationPreferences();

if ($description) {
    $description .= " and {$last}";
} else {
    $description = $last;
}
array_push($descriptions, $last);
?>
@if($preferences->get(NotifyOn::SUMMARY))
The "{{ $summary['build_group'] }}" group has errors, warnings, or test failures.
<?php
$summary['build_summary_url'] = $summary['project_url'] . "&date=" . date('Y-m-d', strtotime($summary['build_time']));
?>
You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.
@else
A submission to CDash for the project {{ $subscription->getProjectName() }} has {{ $description }}. You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.
@endif

Details on the submission can be found at {!!  $summary['build_summary_url']  !!}.

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
<?php $reference = strtolower(str_replace(' ', '_', $topic->getTopicDescription())); ?>
@include("issue.{$reference}", ['items' => $items, 'maxChars' => $project->EmailMaxChars])
<?php echo PHP_EOL; ?>
@endforeach
-CDash on {{ $config->getServer() }}

