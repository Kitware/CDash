<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Messaging\Notification\Email\EmailMessage;
use CDash\Messaging\Topic\TopicInterface;

class TestFailureDecorator extends Decorator
{
    /**
     * It would probably be faster to format this string using sprintf format, but for both
     * extensiblity and readability sake, keeping it readable for now. The tokens are the
     * same tokens used by the Twig templating system.
     *
     * @var string $template
     */
    private $template = "{{ name }} | {{ details }} | ({{ url }})\n";

    protected $description = 'Tests Failing';

    protected $subject = 'FAILED (t={{ count }}): {{ project_name }} - {{ build_name }}';

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return $this->template;
    }

    public function getSubject($project_name, $build_name)
    {
        // TODO: refactor, not
        $search = ['{{ count }}', '{{ project_name }}', '{{ build_name }}'];
        $replace = [$this->rows_processed, $project_name, $build_name];
        $subject = str_replace($search, $replace, $this->subject);
        return $subject;
    }
}
