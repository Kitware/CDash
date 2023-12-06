<?php
namespace CDash\Test\UseCase;

use DOMDocument;
use DOMElement;
use DOMText;
use BuildHandler;

class BuildUseCase extends UseCase
{
    public const WARNING = 0;
    public const ERROR = 1;

    private $command;

    public function __construct(array $properties = [])
    {
        parent::__construct('Failure', $properties);
    }

    public function build(): \AbstractHandler
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $startDateTimeText = date('M d H:i T', $this->startTime);
        $endTimeTest = date('M d H:i T', $this->endTime);

        $site = $xml->appendChild($this->getSiteElement($xml));
        $build = $site->appendChild(new DOMElement('Build'));

        $startDateTime = $build->appendChild(new DOMElement('StartDateTime'));
        $startTestTime = $build->appendChild(new DOMElement('StartBuildTime'));

        $startDateTime->appendChild(new DOMText($startDateTimeText));
        $startTestTime->appendChild(new DOMText($this->startTime));

        $buildCommand = $build->appendChild(new DOMElement('BuildCommand'));
        $buildCommand->appendChild(new DOMText($this->command));

        foreach ($this->getModel('Failure') as $fail) {
            $this->createFailureElement($build, $fail);
        }

        $endDateTime = $build->appendChild(new DOMElement('EndDateTime'));
        $endTestTime = $build->appendChild(new DOMElement('EndBuildTime'));

        $endDateTime->appendChild(new DOMText($endTimeTest));
        $endTestTime->appendChild(new DOMText($this->endTime));

        $xml_str = $xml->saveXML($xml);
        if ($xml_str === false) {
            throw new \Exception('Invalid XML.');
        }
        $handler = new BuildHandler($this->projectId);
        return $this->getXmlHandler($handler, $xml_str);
    }

    protected function createFailureElement(DOMElement $parent, array $attributes): void
    {
        /** @var DOMElement $failure */
        $failure = $parent->appendChild(new DOMElement('Failure'));
        $failure->setAttribute('type', $attributes['type']);

        $this->createActionElement($failure, $attributes)
            ->createCommandElement($failure, $attributes)
            ->createResultElement($failure, $attributes)
            ->createFailureLabelsElement($failure, $attributes);
    }

    protected function createActionElement(DOMElement $parent, array $attributes): self
    {
        $action = $parent->appendChild(new DOMElement('Action'));
        $keys = ['TargetName', 'Language', 'SourceFile', 'OutputFile', 'OutputType'];

        $this->createChildElementsFromKeys($action, $attributes, $keys);
        return $this;
    }

    protected function createCommandElement(DOMElement $parent, array $attributes): self
    {
        $command = $parent->appendChild(new DOMElement('Command'));
        $keys = ['WorkingDirectory', 'Argument'];

        $this->createChildElementsFromKeys($command, $attributes, $keys);
        return $this;
    }

    protected function createResultElement(DOMElement $parent, array $attributes): self
    {
        $result = $parent->appendChild(new DOMElement('Result'));
        $keys = ['StdOut', 'StdErr', 'ExitCondition'];

        $this->createChildElementsFromKeys($result, $attributes, $keys);
        return $this;
    }

    protected function createFailureLabelsElement(DOMElement $parent, array $attributes): void
    {
        if (isset($attributes['Labels'])) {
            $labels = $parent->appendChild(new DOMElement('Labels'));
            foreach ($attributes['Labels'] as $name) {
                $label = $labels->appendChild(new DOMElement('Label'));
                $label->appendChild(new DOMText($name));
            }
        }
    }

    protected function createFailure(array $default_properties): void
    {
        [$type, $properties] = $default_properties;
        $properties['type'] = $type === self::ERROR ? 'Error' : 'Warning';

        $this->createAction($properties)
            ->createCommand($properties)
            ->createResult($properties, $type);

        $this->setModel('Failure', $properties);
    }

    /**
     * TODO: properties like $cmake_dir, $target_name should be instance props so not to re-create per failure basis
     */
    protected function createAction(array &$properties): self
    {
        $faker = $this->getFaker();
        $cmake_dir = '';
        $target_name = '';

        if (!isset($properties['Language'])) {
            $properties['Language'] = 'c++';
        }

        if (!isset($properties['SourceFile'])) {
            $words = $faker->words();
            $dir = array_reduce($words, function ($last, $next) {
                $text = $last ?: '';
                return $text . ucfirst($next);
            });

            $working_dir = "/home/{$faker->firstName()}/$dir/__build";

            $target_name = strtolower($dir);

            $cmake_dir = "{$working_dir}/CmakeFiles/{$dir}.dir";

            $properties['SourceFile'] = "{$working_dir}/{$target_name}.{$properties['Language']}";
        }

        if (!isset($properties['TargetName'])) {
            $properties['TargetName'] = $target_name;
        }


        if (!isset($properties['OutputFile'])) {
            $properties['OutputFile'] = $cmake_dir;
        }

        if (!isset($properties['OutputType'])) {
            $properties['OutputType'] = 'object file';
        }

        return $this;
    }

    protected function createCommand(array &$properties): self
    {
        if (!isset($properties['WorkingDirectory'])) {
            $properties['WorkingDirectory'] = pathinfo($properties['SourceFile'], PATHINFO_DIRNAME);
        }

        if (!isset($properties['Argument'])) {
            $faker = $this->getFaker();
            $args = [];
            $args[] = "/usr/bin/{$properties['Language']}";
            for ($i = 0; $i < $faker->randomDigitNotNull(); $i++) {
                $args[] = "-W{$faker->word()}";
            }
            $args[] = $properties['SourceFile'];
            $properties['Argument'] = $args;
        } elseif (is_string($properties['Argument'])) {
            // let's allow for setting the argument as a string of text
            $args = explode(' ', $properties['Argument']);
            $properties['Argument'] = $args;
        }

        return $this;
    }

    protected function createResult(array &$properties, $type): self
    {
        $faker = $this->getFaker();
        $condition = $type === self::ERROR ? $faker->numberBetween(1, 127) : 0;
        if (!isset($properties['ExitCondition'])) {
            $properties['ExitCondition'] = $condition;
        }

        if ($condition && !isset($properties['StdErr'])) {
            $properties['StdErr'] = $faker->text();
        }

        return $this;
    }

    public function setBuildCommand(string $command): self
    {
        $this->command = $command;
        return $this;
    }

    public function createBuildFailureError(string $name, array $properties = []): self
    {
        $this->setNameInLabels($name, $properties);
        $this->createFailure([self::ERROR, $properties]);
        return $this;
    }

    public function createBuildFailureWarning(string $name, array $properties = []): self
    {
        $this->setNameInLabels($name, $properties);
        $this->createFailure([self::WARNING, $properties]);
        return $this;
    }
}
