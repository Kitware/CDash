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

// Special handling for Update
// TODO: refactor
if (isset($fixes['UpdateError'])) {
    $fixes = $fixes['UpdateError'];
}

$total = [];
if (isset($fixes['BuildError']['fixed']) && $fixes['BuildError']['fixed'] > 0) {
    $total[] = "e={$fixes['BuildError']['fixed']}";
}

if (isset($fixes['BuildWarning']['fixed']) && $fixes['BuildWarning']['fixed'] > 0) {
    $total[] = "w={$fixes['BuildWarning']['fixed']}";
}

if (isset($fixes['TestFailure'])) {
    $count = 0;
    if (isset($fixes['TestFailure']['failed']['fixed']) && $fixes['TestFailure']['failed']['fixed'] > 0) {
        $total[] = "t={$fixes['TestFailure']['failed']['fixed']}";
    }
    if(isset($fixes['TestFailure']['notrun']['fixed']) && $fixes['TestFailure']['notrun']['fixed'] > 0) {
        $total[] = "m={$fixes['TestFailure']['notrun']['fixed']}";
    }
}
$total = implode(", ", $total);
?>
PASSED ({{ $total }}): {{ $project }} - {{ $build }} - {{ $group }}
