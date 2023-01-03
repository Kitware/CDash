<?php
namespace CDash\Test\UseCase;

use AbstractHandler;
use CDash\Test\CDashUseCaseTestCase;
use DOMDocument;
use DOMElement;
use DOMText;

abstract class UseCase
{
    /* actionable steps */
    const TEST = 'Test';
    const CONFIG = 'Config';
    const UPDATE = 'Update';
    const BUILD = 'Build';
    const DYNAMIC_ANALYSIS = 'DynamicAnalysis';

    /* build types (modes) */
    const NIGHTLY = 'Nightly';
    const CONTINUOUS = 'Continuous';
    const EXPERIMENTAL = 'Experimental';

    private $faker;
    private $ids;
    protected $subprojects = [];
    protected $properties = [];
    protected $projectId = 321;
    protected $startTime;
    protected $endTime;

    protected $authors = [];
    protected $testCase;

    abstract public function build();

    public function __construct($name, array $properties = [])
    {
        $this->properties[$name] = $properties;

        $this->setStartTime(time());
        $this->setEndTime(time()+1);
    }

    /**
     * @param CDashUseCaseTestCase $testCase
     * @param $type
     * @return TestUseCase|ConfigUseCase|UpdateUseCase|BuildUseCase
     */
    public static function createBuilder(CDashUseCaseTestCase $testCase, $type)
    {
        switch ($type) {
            case self::TEST:
                $useCase = new TestUseCase();
                break;
            case self::CONFIG:
                $useCase = new ConfigUseCase();
                break;
            case self::UPDATE:
                $useCase = new UpdateUseCase();
                break;
            case self::BUILD:
                $useCase = new BuildUseCase();
                break;
            case self::DYNAMIC_ANALYSIS:
                $useCase = new DynamicAnalysisUseCase();
                break;
            default:
                $useCase = null;
        }
        $testCase->setUseCaseModelFactory($useCase);
        return $useCase;
    }

    /**
     * @param $start_time
     * @return self
     */
    public function setStartTime($start_time)
    {
        $this->startTime = $start_time;
        return $this;
    }

    /**
     * @param $end_time
     * @return self
     */
    public function setEndTime($end_time)
    {
        $this->endTime = $end_time;
        return $this;
    }

    public function setAuthors(array $authors)
    {
        $this->authors = $authors;
        return $this;
    }

    public function getAuthors($build)
    {
        if (isset($this->authors[$build])) {
            return $this->authors[$build];
        }
        return [];
    }

    public function createAuthor($author, array $builds = [])
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

    /**
     * @param $class_name
     * @return mixed
     */
    public function getIdForClass($class_name)
    {
        if (!isset($this->ids[$class_name])) {
            $this->ids[$class_name] = 0;
        }
        return ++$this->ids[$class_name];
    }

    /**
     * @param $projectId
     * @return $this
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
        return $this;
    }

    /**
     * Sets a site attribute, BuildStamp, for example. Because there is only one site per
     * submission, all work is performed only on the first entry of the array.
     *
     * @param $attribute
     * @param $value
     * @return $this
     */
    public function setSiteAttribute($attribute, $value)
    {
        if (!isset($this->properties['Site'][0])) {
            $this->properties['Site'][0] = [];
        }
        $this->properties['Site'][0][$attribute] = $value;
        return $this;
    }

    /**
     * @param $tag_name
     * @param array $properties
     * @return $this
     */
    public function setModel($tag_name, array $properties)
    {
        if (!isset($this->properties[$tag_name])) {
            $this->properties[$tag_name] = [];
        }

        $this->properties[$tag_name][] = $properties;

        return $this;
    }

    public function getModel($tag_name)
    {
        $model = [];
        if (isset($this->properties[$tag_name])) {
            $model = $this->properties[$tag_name];
        }
        return $model;
    }

    /**
     * @param array $properties
     * @return $this
     */
    public function createSite(array $properties)
    {
        $this->setModel('Site', $properties);
        return $this;
    }

    /**
     * @param $name
     * @param array $labels
     * @return $this
     */
    public function createSubproject($name, array $labels = [])
    {
        if (empty($labels)) {
            $labels[] = $name;
        }

        $this->subprojects[$name] = $labels;
        return $this;
    }

    /**
     * @param AbstractHandler $handler
     * @param $xml
     * @return AbstractHandler
     */
    public function getXmlHandler(AbstractHandler $handler, $xml)
    {
        $parser = xml_parser_create();
        xml_set_element_handler(
            $parser,
            array($handler, 'startElement'),
            array($handler, 'endElement')
        );
        xml_set_character_data_handler($parser, array($handler, 'text'));
        xml_parse($parser, $xml, false);
        return $handler;
    }

    /**
     * @param DOMDocument $document
     * @return DOMElement
     * @throws \Exception
     */
    protected function getSiteElement(DOMDocument $document)
    {
        if (!isset($this->properties['Site'])) {
            throw new \Exception('Site properties not initialized');
        }

        /** @var DOMElement $site $site */
        $site = $document->createElement('Site');
        foreach ($this->properties['Site'][0] as $name => $value) {
            $site->setAttribute(strtolower($name), $value);
        }

        if (empty($site->getAttribute('name'))) {
            throw new \Exception('Name attribute required for Site');
        }

        foreach ($this->subprojects as $name => $labels) {
            /** @var DOMElement $subproject */
            $subproject = $site->appendChild(new DOMElement('Subproject'));
            $subproject->setAttribute('name', $name);
            $this->createLabelsElement($subproject, $labels);
        }
        return $site;
    }

    /**
     * @param DOMElement $parent
     * @param array $label_names
     */
    protected function createLabelsElement(DOMElement $parent, array $label_names)
    {
        foreach ($label_names as $name) {
            /** @var DOMElement $label */
            $label = $parent->appendChild(new DOMElement('Label'));
            $label->appendChild(new DOMText($name));
        }
    }

    protected function createElapsedMinutesElement(DOMElement $parent)
    {
        $elapsed = 0;
        if ($this->startTime && $this->endTime) {
            $total = $this->endTime - $this->startTime;
            $elapsed = $total / 60;
        }
        $node = $parent->appendChild(new DOMElement('Elapsed'));
        $node->appendChild(new DOMText($elapsed));
    }

    /**
     * @param $test_name
     * @param array $properties
     * @return $this
     */
    public function setTestProperties($test_name, array $properties)
    {
        foreach ($this->properties['Test'] as &$test) {
            if ($test['Name'] === $test_name) {
                $test = array_merge($test, $properties);
            }
        }
        return $this;
    }

    /**
     * @param string $command
     * @return $this
     */
    public function setConfigureCommand($command)
    {
        $this->properties['Config']['command'] = $command;
        return $this;
    }

    /**
     * @param int $status
     * @return $this
     */
    public function setConfigureStatus($status)
    {
        $this->properties['Config']['status'] = $status;
        return $this;
    }

    /**
     * @param string $log
     * @return $this
     */
    public function setConfigureLog($log)
    {
        $this->properties['Config']['log'] = $log;
        return $this;
    }

    /**
     * @param int $minutes
     * @return $this
     */
    public function setConfigureElapsedMinutes($minutes)
    {
        $this->properties['Config']['elapsed'] = $minutes;
        return $this;
    }

    /**
     * Checks if an array is associative, sequential or a mixture of both. Will return true
     * only if all array keys are ints.
     *
     * @param array $array
     * @return bool
     * @ref https://gist.github.com/Thinkscape/1965669
     */
    public function isSequential(array $array)
    {
        return $array === array_values($array);
    }

    public function getFaker()
    {
        if (!$this->faker) {
            $this->faker = \Faker\Factory::create();
        }
        return $this->faker;
    }

    public function createChildElementsFromKeys(DOMElement $parent, array $attributes, $keys = [])
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

    protected function setNameInLabels($name, array &$properties)
    {
        if ($name) {
            if (isset($properties['Labels']) && is_array($properties['Labels'])) {
                $properties['Labels'][] = $name;
            } else {
                $properties['Labels'] = [$name];
            }
        }
    }
}
