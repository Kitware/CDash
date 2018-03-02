<?php
namespace CDash\Test\UseCase;

use AbstractHandler;
use CDash\Test\CDashUseCaseTestCase;
use DOMDocument;
use DOMElement;
use DOMText;

abstract class UseCase
{
    const TEST = 1;

    private $ids;
    protected $subprojects = [];
    protected $properties = [];
    protected $projectId = 321;
    protected $scheduleId = 0;
    protected $startTime;
    protected $endTime;

    protected $testCase;

    abstract public function build();

    /**
     * @param CDashUseCaseTestCase $testCase
     * @param $type
     * @return UseCase
     */
    public static function createBuilder(CDashUseCaseTestCase $testCase, $type)
    {
        switch ($type) {
            case self::TEST:
                $useCase = new TestUseCase();
                $testCase->setUseCaseModelFactory($useCase);
                return $useCase;
        }
    }

    /**
     * @param $start_time
     * @return $this
     */
    public function setStartTime($start_time)
    {
        $this->startTime = $start_time;
        return $this;
    }

    /**
     * @param $end_time
     * @return $this
     */
    public function setEndTime($end_time)
    {
        $this->endTime = $end_time;
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
     * @param $class_name
     * @param array $properties
     * @return $this
     */
    public function setModel($class_name, array $properties)
    {
        if (!isset($this->properties[$class_name])) {
            $this->properties[$class_name] = [];
        }

        $this->properties[$class_name][] = $properties;

        return $this;
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
     * @param array $properties
     * @return UseCase
     */
    public function createTest(array $properties)
    {
        if (isset($properties[0])) {
            $hash = [
                'Name' => $properties[0],
                'Status' => $properties[1],
            ];
            unset($properties[0], $properties[1]);
            $properties = array_merge($hash, $properties);
        }

        // create some realistic defaults if properties don't exist
        if (!isset($properties['Path'])) {
            $properties['Path'] = '/a/path/to/test';
        }

        if (!isset($properties['FullName'])) {
            $properties['FullName'] = "{$properties['Path']}/{$properties['Name']}";
        }

        if (!isset($properties['FullCommandLine'])) {
            $properties['FullCommandLine'] = "{$properties['FullName']} --run-test .";
        }

        $this->setModel('Test', $properties);
        return $this;
    }

    /**
     * @param $name
     * @param array $labels
     * @return $this
     */
    public function createTestPassed($name, array $labels = [])
    {
        $this->createTest([$name, TestUseCase::PASSED, 'Labels' => $labels]);
        return $this;
    }

    /**
     * @param $name
     * @param array $labels
     * @return $this
     */
    public function createTestFailed($name, array $labels = [])
    {
        $this->createTest([$name, TestUseCase::FAILED, 'Labels' => $labels]);
        return $this;
    }

    /**
     * @param $name
     * @param array $labels
     * @return $this
     */
    public function createTestNotRun($name, array $labels = [])
    {
        $this->createTest([$name, TestUseCase::NOTRUN, 'Labels' => $labels]);
        return $this;
    }

    /**
     * @param $name
     * @return UseCase
     */
    public function createTestTimedout($name, array $labels = [])
    {
        $this->createTest([$name, TestUseCase::TIMEOUT, 'Labels' => $labels]);
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
}
