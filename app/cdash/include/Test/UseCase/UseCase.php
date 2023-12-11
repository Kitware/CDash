<?php
namespace CDash\Test\UseCase;

use AbstractHandler;
use CDash\Test\CDashUseCaseTestCase;
use DOMDocument;
use DOMElement;
use DOMText;
use Exception;
use InvalidArgumentException;

abstract class UseCase
{
    /* actionable steps */
    public const TEST = 'Test';
    public const CONFIG = 'Config';
    public const UPDATE = 'Update';
    public const BUILD = 'Build';
    public const DYNAMIC_ANALYSIS = 'DynamicAnalysis';

    /* build types (modes) */
    public const NIGHTLY = 'Nightly';
    public const CONTINUOUS = 'Continuous';
    public const EXPERIMENTAL = 'Experimental';

    private $faker;
    private array $ids = [];
    private array $subprojects = [];
    protected $properties = [];
    protected int $projectId = 321;
    protected $startTime;
    protected $endTime;

    protected array $authors = [];

    abstract public function build(): AbstractHandler;

    public function __construct($name, array $properties = [])
    {
        $this->properties[$name] = $properties;

        $this->setStartTime(time());
        $this->setEndTime(time()+1);
    }

    public static function createBuilder(CDashUseCaseTestCase $testCase, $type): TestUseCase|ConfigUseCase|UpdateUseCase|BuildUseCase|DynamicAnalysisUseCase
    {
        $useCase = match ($type) {
            self::TEST => new TestUseCase(),
            self::CONFIG => new ConfigUseCase(),
            self::UPDATE => new UpdateUseCase(),
            self::BUILD => new BuildUseCase(),
            self::DYNAMIC_ANALYSIS => new DynamicAnalysisUseCase(),
            default => throw new InvalidArgumentException('Invalid UseCase type.'),
        };
        $testCase->setUseCaseModelFactory($useCase);
        return $useCase;
    }

    public function setStartTime($start_time): self
    {
        $this->startTime = $start_time;
        return $this;
    }

    public function setEndTime($end_time): self
    {
        $this->endTime = $end_time;
        return $this;
    }

    public function getAuthors($build)
    {
        if (isset($this->authors[$build])) {
            return $this->authors[$build];
        }
        return [];
    }

    public function createAuthor(string $author, array $builds = []): self
    {
        $builds = empty($builds) ? ['all'] : $builds;
        foreach ($builds as $build) {
            if (!isset($this->authors[$build])) {
                $this->authors[$build] = [];
            }
            $this->authors[$build][] = $author;
        }
        return $this;
    }

    public function getIdForClass(string $class_name): int
    {
        if (!isset($this->ids[$class_name])) {
            $this->ids[$class_name] = 0;
        }
        return (int) ++$this->ids[$class_name];
    }

    public function setProjectId($projectId): self
    {
        $this->projectId = (int) $projectId;
        return $this;
    }

    /**
     * Sets a site attribute, BuildStamp, for example. Because there is only one site per
     * submission, all work is performed only on the first entry of the array.
     */
    public function setSiteAttribute(string $attribute, mixed $value): self
    {
        if (!isset($this->properties['Site'][0])) {
            $this->properties['Site'][0] = [];
        }
        $this->properties['Site'][0][$attribute] = $value;
        return $this;
    }

    public function setModel(string $tag_name, array $properties): self
    {
        if (!isset($this->properties[$tag_name])) {
            $this->properties[$tag_name] = [];
        }

        $this->properties[$tag_name][] = $properties;

        return $this;
    }

    public function getModel(string $tag_name)
    {
        $model = [];
        if (isset($this->properties[$tag_name])) {
            $model = $this->properties[$tag_name];
        }
        return $model;
    }

    public function createSite(array $properties): self
    {
        $this->setModel('Site', $properties);
        return $this;
    }

    public function createSubproject(string $name, array $labels = []): self
    {
        if (empty($labels)) {
            $labels[] = $name;
        }

        $this->subprojects[$name] = $labels;
        return $this;
    }

    public function getXmlHandler(AbstractHandler $handler, string $xml): AbstractHandler
    {
        $parser = xml_parser_create();
        xml_set_element_handler(
            $parser,
            [$handler, 'startElement'],
            [$handler, 'endElement']
        );
        xml_set_character_data_handler($parser, [$handler, 'text']);
        xml_parse($parser, $xml);
        return $handler;
    }

    protected function getSiteElement(DOMDocument $document): DOMElement
    {
        if (!isset($this->properties['Site'])) {
            throw new Exception('Site properties not initialized');
        }

        /** @var DOMElement $site $site */
        $site = $document->createElement('Site');
        foreach ($this->properties['Site'][0] as $name => $value) {
            $site->setAttribute(strtolower($name), $value);
        }

        if (empty($site->getAttribute('name'))) {
            throw new Exception('Name attribute required for Site');
        }

        /**
         * @var string $name
         */
        foreach ($this->subprojects as $name => $labels) {
            /** @var DOMElement $subproject */
            $subproject = $site->appendChild(new DOMElement('Subproject'));
            $subproject->setAttribute('name', $name);
            $this->createLabelsElement($subproject, $labels);
        }
        return $site;
    }

    protected function createLabelsElement(DOMElement $parent, array $label_names): void
    {
        foreach ($label_names as $name) {
            /** @var DOMElement $label */
            $label = $parent->appendChild(new DOMElement('Label'));
            $label->appendChild(new DOMText($name));
        }
    }

    protected function createElapsedMinutesElement(DOMElement $parent): void
    {
        $elapsed = 0;
        if ($this->startTime && $this->endTime) {
            $total = $this->endTime - $this->startTime;
            $elapsed = $total / 60;
        }
        $node = $parent->appendChild(new DOMElement('Elapsed'));
        $node->appendChild(new DOMText($elapsed));
    }

    public function setTestProperties(string $test_name, array $properties): self
    {
        foreach ($this->properties['Test'] as &$test) {
            if ($test['Name'] === $test_name) {
                $test = array_merge($test, $properties);
            }
        }
        return $this;
    }

    public function setConfigureCommand(string $command): self
    {
        $this->properties['Config']['command'] = $command;
        return $this;
    }

    public function setConfigureStatus(int $status): self
    {
        $this->properties['Config']['status'] = $status;
        return $this;
    }

    public function setConfigureLog(string $log): self
    {
        $this->properties['Config']['log'] = $log;
        return $this;
    }

    public function getFaker()
    {
        if (!$this->faker) {
            $this->faker = \Faker\Factory::create();
        }
        return $this->faker;
    }

    public function createChildElementsFromKeys(DOMElement $parent, array $attributes, $keys = []): void
    {
        $subset = array_filter(
            $attributes,
            function ($key) use ($keys) {
                return empty($keys) || in_array($key, $keys);
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($subset as $key => $values) {
            $values = is_array($values) ? $values : [$values];
            foreach ($values as $value) {
                $node = $parent->appendChild(new DOMElement($key));
                if ($value) {
                    $node->appendChild(new DOMText($value));
                }
            }
        }
    }

    protected function setNameInLabels(string $name, array &$properties): void
    {
        if ($name !== '') {
            if (isset($properties['Labels']) && is_array($properties['Labels'])) {
                $properties['Labels'][] = $name;
            } else {
                $properties['Labels'] = [$name];
            }
        }
    }
}
