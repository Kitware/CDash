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
    protected $tests = [];
    protected $subprojects = [];
    protected $properties = [];
    protected $projectId = 321;
    protected $scheduleId = 0;

    private $siteAttributes = [];

    protected $testCase;

    abstract public function build();

    /**
     * @param CDashUseCaseTestCase $testCase
     * @param $type
     * @return UseCase
     */
    public static function createBuilder(CDashUseCaseTestCase $testCase, $type)
    {
        switch ($type)
        {
            case self::TEST:
                $useCase = new TestUseCase();
                $testCase->setUseCaseModelFactory($useCase);
                return $useCase;
        }
    }

    public function getIdForClass($class_name)
    {
        if (!isset($this->ids[$class_name])) {
            $this->ids[$class_name] = 0;
        }
        return ++$this->ids[$class_name];
    }

    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
        return $this;
    }


    public function setSiteAttribute($attribute, $value)
    {
        $this->siteAttributes[$attribute] = $value;
        return $this;
    }

    public function setModel($class_name, array $properties)
    {
        if (!isset($this->properties[$class_name])) {
            $this->properties[$class_name] = [];
        }

        /*
        $model = $this->testCase->getMockBuilder($class_name)
            ->disableOriginalConstructor()
            ->getMock();
        $model_properties = get_object_vars($model);

        foreach ($properties as $property_name => $property) {
            if (in_array($property_name, $model_properties)) {
                $model->$property_name = $property;
            }
        }
        */

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

    public function createSubproject($name, array $labels = [])
    {
        if (empty($labels)) {
            $labels[] = $name;
        }

        $this->subprojects[$name] = $labels;
        return $this;
    }

    public function assignToSubproject($subproject, array $subjects = [])
    {
        $label = isset($subjects['label']) ? $subjects['label'] : $subproject;

        if (!isset($this->subprojects[$subproject])) {
            throw new \Exception("Subproject {$subproject} does not exist");
        }

        if (!in_array($label, $this->subprojects[$subproject])) {
            throw new \Exception("{$label} is not a label of {$subproject}");
        }

        foreach ($subjects as $type => $subject) {
            if ($type === 'label') {
                continue;
            }

            $type = ucfirst($type);
            if (!isset($this->properties[$type])) {
                throw new \Exception("{$type} is not a property of UseCase");
            }

            foreach ($this->properties[$type] as &$entry) {
                if ($subject === $entry['Name']) {
                    if (!isset($entry['Labels'])) {
                        $entry['Labels'] = [];
                    }

                    $entry['Labels'][] = $label;
                    break;
                }
            }
        }
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

    protected function createLabelsElement(DOMElement $parent, array $label_names)
    {
        foreach ($label_names as $name) {
            /** @var DOMElement $label */
            $label = $parent->appendChild(new DOMElement('Label'));
            $label->appendChild(new DOMText($name));
        }
    }
}
