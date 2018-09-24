<?php
$summary = $subscription->getBuildSummary();
$project = $subscription->getProjectName();
$labels = $subscription->getSubscriber()->getLabels();
$subprojects = $summary['build_subproject_names'];

// What's happening here is a little strange. What we're trying to accomplish is this:
// if a user is subscribed to just one subproject, concat the subproject's name to the
// project in the subject. The problem with just testing the number of label subscriptions
// is that the user may have other criteria, besides label subscriptions, that increase
// the number of subprojects associated with this notification.
// TODO: verify this is the correct logic?
$project .= count($subprojects) === 1 ? "/{$subprojects[0]}" : '';

$totals = [];
foreach($summary['topics'] as $topic) {
    $description = $topic['description'];
    switch ($description) {
        case 'Failing Tests':
            $totals[] = "t={$topic['count']}";
            break;
        case 'Missing Tests':
            $totals[] = "m={$topic['count']}";
            break;
        case 'Configure Errors':
            $totals[] = "c={$topic['count']}";
            break;
        case 'Warnings':
            $totals[] = "w=${topic['count']}";
            break;
        case 'Errors':
            $totals[] = "b={$topic['count']}";
            break;
        case 'Dynamic analysis tests failing or not run':
            $totals[] = "d={$topic['count']}";
    }
}

$build = $summary['build_name'];
$group = $summary['build_type'];
$total = implode(', ', $totals);
?>
FAILED ({{ $total }}): {{ $project }} - {{ $build }} - {{ $group }}
