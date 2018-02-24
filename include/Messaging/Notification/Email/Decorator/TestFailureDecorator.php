<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Messaging\Topic\Topic;

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

    public function addSubject($subject)
    {
        /** @var Topic $subject */
        $tests = $subject->getTopicCollection();
        $counter = 0;
        $data = [];

        foreach ($tests as $test) {
            $data[] = [
                'name' => $test->Name,
                'details' => $test->Details,
                'url' => $test->GetUrl(),
            ];
            if (++$counter === $this->maxTopicItems) {
                break;
            }
        }

        $maxReachedText = $this->maxTopicItems < $tests->count() ?
            " (first $this->maxTopicItems included)" : '';
        $this->text = "\n*Tests Failing*{$maxReachedText}\n{$this->decorateWith($this->template, $data)}\n";
        return $this->text;
    }
}
