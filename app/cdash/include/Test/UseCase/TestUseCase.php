<?php
namespace CDash\Test\UseCase;

use DOMDocument;
use DOMElement;
use DOMText;
use TestingHandler;

class TestUseCase extends UseCase
{
    const EXIT_CODE = 'Exit Code';
    const EXIT_VALUE = 'Exit Value';
    const EXE_TIME = 'Execution Time';
    const COMPLETION_STATUS = 'Completion Status';
    const CMD_LINE = 'Command Line';

    const TEXT_STRING = 'text/string';
    const NUM_DOUBLE = 'numeric/double';

    const FAILED = 'failed';
    const PASSED = 'passed';
    const OTHERFAULT = 'OTHER_FAULT';
    const TIMEOUT = 'Timeout';
    const NOTRUN = 'notrun';

    public function __construct(array $properties = [])
    {
        parent::__construct('Test', $properties);
    }

    /**
     * @return \AbstractHandler
     * @throws \Exception
     */
    public function build()
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $startDateTimeText = date('M d H:i T', $this->startTime);
        $endTimeTest = date('M d H:i T', $this->endTime);

        $site = $xml->appendChild($this->getSiteElement($xml));
        $testing = $site->appendChild(new DOMElement('Testing'));

        $startDateTime = $testing->appendChild(new DOMElement('StartDateTime'));
        $startTestTime = $testing->appendChild(new DOMElement('StartTestTime'));

        $startDateTime->appendChild(new DOMText($startDateTimeText));
        $startTestTime->appendChild(new DOMText($this->startTime));

        $tests = $this->getModel('Test');

        foreach ($tests as $test) {
            $this->createTestElement($testing, $test);
        }

        $endDateTime = $testing->appendChild(new DOMElement('EndDateTime'));
        $endTestTime = $testing->appendChild(new DOMElement('EndTestTime'));

        $endDateTime->appendChild(new DOMText($endTimeTest));
        $endTestTime->appendChild(new DOMText($this->endTime));

        $xml_str = $xml->saveXML($xml);
        $handler = new TestingHandler($this->projectId);
        return $this->getXmlHandler($handler, $xml_str);
    }

    /**
     * @param DOMElement $parent
     * @param $attributes
     */
    protected function createTestElement(DOMElement $parent, $attributes)
    {
        $path_info = pathinfo($attributes['FullName']);

        /** @var DOMElement $test */
        $test = $parent->appendChild(new DOMElement('Test'));
        $this->setTestStatus($test, $attributes['Status']);

        // $test->setAttribute('Status', $status_text);

        $name = $test->appendChild(new DOMElement('Name'));
        $name->appendChild(new DOMText($attributes['Name']));

        $path = $test->appendChild(new DOMElement('Path'));
        $path->appendChild(new DOMText($path_info['dirname']));

        $fullname = $test->appendChild(new DOMElement('FullName'));
        $fullname->appendChild(new DOMText($attributes['FullName']));

        $commandLine = $test->appendChild(new DOMElement('FullCommandLine'));
        $commandLine->appendChild(new DOMText($attributes['FullCommandLine']));

        $this->createResultsElement($test, $attributes);
        if (isset($attributes['Labels']) && !empty($attributes['Labels'])) {
            $labels = $test->appendChild(new DOMElement('Labels'));
            $this->createLabelsElement($labels, $attributes['Labels']);
        }
    }

    /**
     * @param DOMElement $test
     * @param $status
     */
    protected function setTestStatus(DOMElement $test, $status)
    {
        switch ($status) {
            case self::FAILED:
            case self::TIMEOUT:
            case self::OTHERFAULT:
                $test->setAttribute('Status', self::FAILED);
                break;
            case self::NOTRUN:
                $test->setAttribute('Status', self::NOTRUN);
                break;
            case self::PASSED:
                $test->setAttribute('Status', self::PASSED);
        }
    }

    /**
     * @param DOMElement $parent
     * @param $attributes
     */
    protected function createResultsElement(DOMElement $parent, $attributes)
    {
        $results = $parent->appendChild(new DOMElement('Results'));

        if ($attributes['Status'] === self::NOTRUN) {
            return;
        }

        /** @var DOMElement $status */
        $status = $results->appendChild(new DOMElement('NamedMeasurement'));
        $status->setAttribute('name', 'Completion Status');
        $status->setAttribute('type', 'text/string');
        $status_value = $status->appendChild(new DOMElement('Value'));
        $status_value->appendChild(new DOMText('Completed'));

        /** @var DOMElement $code */
        $code = $results->appendChild(new DOMElement('NamedMeasurement'));
        $code->setAttribute('name', 'Exit Code');
        $code->setAttribute('type', 'text/string');
        $code_value = $code->appendChild(new DOMElement('Value'));

        /** @var DOMElement $exectime */
        $exectime_text = isset($attributes['Execution Time']) ?
            $attributes['Execution Time'] : '0.012004';

        $exectime = $results->appendChild(new DOMElement('NamedMeasurement'));
        $exectime->setAttribute('name', 'Execution Time');
        $exectime->setAttribute('type', 'numeric/double');
        $exectime_value = $exectime->appendChild(new DOMElement('Value'));
        $exectime_value->appendChild(new DOMText($exectime_text));

        /** @var DOMElement $exit */
        $exit = $results->appendChild(new DOMElement('NamedMeasurement'));
        $exit->setAttribute('name', 'Exit Value');
        $exit->setAttribute('type', 'text/string');
        $exit_value = $results->appendChild(new DOMElement('Value'));

        /** @var DOMElement $measurement */
        $measurement = $results->appendChild(new DOMElement('Measurement'));
        $measurement_value = $measurement->appendChild(new DOMElement('Value'));

        switch ($attributes['Status']) {
            case self::PASSED:
                $measurement_value->appendChild(new DOMText('PASS'));
                break;
            case self::FAILED:
                $code_value->appendChild(new DOMText('Failed'));
                $exit_value->appendChild(new DOMText('127'));
                $measurement_value->appendChild(new DOMText('FAIL'));
                break;
            case self::TIMEOUT:
                $code_value->appendChild(new DOMText('Timeout'));
                $exit_value->appendChild(new DOMText('127'));
                $measurement_value->appendChild(new DOMText('TIMEOUT'));
                break;
            case self::OTHERFAULT:
                $code_value->appendChild(new DOMText('OTHER_FAULT'));
                $exit_value->appendChild(new DOMText('127'));
                $measurement_value->appendChild(new DOMText('Segmentation fault: exited with 127'));
        }
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

        $properties['ProjectId'] = $this->projectId;

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
}
