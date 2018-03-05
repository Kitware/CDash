<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Messaging\Subscription\SubscriptionInterface;
use CDash\Messaging\Topic\Topic;

class SummaryDecorator extends Decorator
{
    private $counts_template = 'Total {{ description }}: {{ count }}';
    private $summary_template = [
        'Project: {{ project_name }}%s',
        'Site: {{ site_name }}',
        'Build Name: {{ build_name }}',
        'Build Time: {{ build_time }}',
        'Type: {{ build_type }}',
        '{{ counts }}',
        '',
    ];

    /**
     * @param SubscriptionInterface $subscription
     * @return string
     */
    public function createSummary(SubscriptionInterface $subscription)
    {
        $summary = $subscription->getBuildSummary();

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

        // Add the name of the subproject if there is only one
        $subproject = count($summary['build_subproject_names']) === 1 ?
            "\nSubProject Name: {$summary['build_subproject_names'][0]}" :
            '';
        $template = sprintf($template, $subproject);
        $this->text = $this->decorateWith($template, $data);
        return $this->text;
    }

    /**
     * @param Topic $topic
     * @return string|void
     */
    public function setTopic(Topic $topic)
    {
        // TODO: Implement setTopic() method.
    }
}
