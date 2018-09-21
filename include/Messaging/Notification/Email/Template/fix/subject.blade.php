<?php
$summary = $subscription->getBuildSummary();
$project = $summary['project_name'];
$labels = $subscription->getSubscriber()->getLabels();
$subprojects = $summary['build_subproject_names'];
$project .= count($labels) === 1 && count($subprojects) === 1 ?
    "/{$labels[0]}" : '';

$build = $summary['build_name'];
$group = $summary['build_type'];
$total = "not yet implemented";
?>
PASSED ({{ $total }}): {{ $project }} - {{ $build }} - {{ $group }}
