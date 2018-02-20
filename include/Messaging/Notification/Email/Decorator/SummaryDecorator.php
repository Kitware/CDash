<?php
namespace CDash\Messaging\Notification\Email\Decorator;

class SummaryDecorator extends Decorator
{
    private $counts_template = 'Total {{ description }}: {{ count }}';
    private $summary_template = [
        'Project: {{ project_name }}',
        'Site: {{ site_name }}',
        'Build Name: {{ build_name }}',
        'Build Time: {{ build_time }}',
        'Type: {{ build_type }}',
        '{{ counts }}',
        '',
    ];

    public function addSubject($subject)
    {
        $summary = $subject->getBuildSummary();

        $counts = $this->decorateWith($this->counts_template, array_values($summary['topics']));
        $data = [
            'project_name' => $summary['project_name'],
            'site_name' => $summary['site_name'],
            'build_name' => $summary['build_name'],
            'build_time' => $summary['build_time'],
            'build_type' => $summary['build_type'],
            'counts' => $counts,
        ];
        $template = implode("\n", $this->summary_template);
        $this->text = $this->decorateWith($template, $data);
    }
}
