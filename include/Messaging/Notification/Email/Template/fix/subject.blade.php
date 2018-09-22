<?php
$summary = $subscription->getBuildSummary();
$project = $summary['project_name'];
$labels = $subscription->getSubscriber()->getLabels();
$subprojects = $summary['build_subproject_names'];
$project .= count($labels) === 1 && count($subprojects) === 1 ?
    "/{$labels[0]}" : '';

$build = $summary['build_name'];
$group = $summary['build_type'];
$fixes = $summary['fixes'];
$total = [];
if (isset($fixes['BuildError']['fixed'])) {
    $total[] = "e={$fixes['BuildError']['fixed']}";
}

if (isset($fixes['BuildWarning']['fixed'])) {
    $total[] = "w={$fixes['BuildWarning']['fixed']}";
}

if (isset($fixes['TestFailure'])) {
    $count = 0;
    if (isset($fixes['TestFailure']['failed']['fixed'])) {
        $count += $fixes['TestFailure']['failed']['fixed'];
    }
    if(isset($fixes['TestFailure']['notrun']['fixed'])) {
        $count += $fixes['TestFailure']['notrun']['fixed'];
    }
    if ($count) {
        $total[] = "t={$count}";
    }
}
$total = implode(", ", $total);
?>
PASSED ({{ $total }}): {{ $project }} - {{ $build }} - {{ $group }}
