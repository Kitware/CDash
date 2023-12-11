<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Test\UseCase;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DynamicAnalysisHandler;

class DynamicAnalysisUseCase extends UseCase
{
    public const FAILED = 'failed';
    public const PASSED = 'passed';

    private $working_directory;
    private $checker;

    public function __construct(array $properties = [])
    {
        parent::__construct('DynamicAnalysis', $properties);
        $faker = parent::getFaker();
        $this->working_directory = $properties['WorkingDirectory'] ??
            "/users/{$faker->firstName}/{$faker->word}";
        $this->checker = '/usr/bin/valgrind';
    }

    public function build(): \AbstractHandler
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $startDateTimeText = date('M d H:i T', $this->startTime);
        $endTimeTest = date('M d H:i T', $this->endTime);

        $site = $xml->appendChild($this->getSiteElement($xml));

        $tests = $this->getModel('DynamicAnalysis');
        /** @var DOMElement|DOMNode $analysis */
        $analysis = $site->appendChild(new DOMElement('DynamicAnalysis'));
        $analysis->setAttribute('Checker', $this->getTitleForChecker());

        $startDateTime = $analysis->appendChild(new DOMElement('StartDateTime'));
        $startTestTime = $analysis->appendChild(new DOMElement('StartTestTime'));

        $startDateTime->appendChild(new DOMText($startDateTimeText));
        $startTestTime->appendChild(new DOMText($this->startTime));

        $this->createTestListElement($analysis, $tests);

        foreach ($tests as $test) {
            $this->createTestElement($analysis, $test);
        }

        $endDateTime = $analysis->appendChild(new DOMElement('EndDateTime'));
        $endTestTime = $analysis->appendChild(new DOMElement('EndTestTime'));

        $endDateTime->appendChild(new DOMText($endTimeTest));
        $endTestTime->appendChild(new DOMText($this->endTime));

        $this->createElapsedMinutesElement($analysis);

        $xml_str = $xml->saveXML($xml);
        if ($xml_str === false) {
            throw new \Exception('Invalid XML.');
        }
        $handler = new DynamicAnalysisHandler($this->projectId);
        return $this->getXmlHandler($handler, $xml_str);
    }

    private function getTitleForChecker(): string
    {
        return ucfirst(strtolower(pathinfo($this->checker, PATHINFO_BASENAME)));
    }

    protected function createTestListElement(DOMElement $parent, array $tests): void
    {
        $testList = $parent->appendChild(new DOMElement('TestList'));
        foreach ($tests as $t) {
            $test = $testList->appendChild(new DOMElement('Test'));
            $test->appendChild(new DOMText($t['FullName']));
        }
    }

    protected function createTestElement(DOMElement $parent, array $t): void
    {
        $test = $parent->appendChild(new DOMElement('Test'));
        $test->setAttribute('Status', $t['Status']);
        $keys = ['Name', 'Path', 'FullName', 'FullCommandLine'];
        $this->createChildElementsFromKeys($test, $t, $keys);

        $log = $test->appendChild(new DOMElement('Log'));
        $log->setAttribute('compression', $t['LogCompression']);
        $log->setAttribute('encoding', $t['LogEncoding']);
        $log->appendChild(new DOMText($t['Log']));

        $this->createResultsElement($test, $t);

        if (isset($t['Labels'])) {
            $labels = $test->appendChild(new DOMElement('Labels'));
            $this->createLabelsElement($labels, $t['Labels']);
        }
    }

    protected function createResultsElement(DOMElement $parent, array $t): void
    {
        $results = $parent->appendChild(new DOMElement('Results'));
        foreach ($t['Results'] as $result) {
            $node = $results->appendChild(new DOMElement('Defect'));
            $node->setAttribute('type', $result['type']);
            $node->appendChild(new DOMText($result['value']));
        }
    }

    public function setChecker(string $checker): self
    {
        $this->checker = $checker;
        return $this;
    }

    public function createFailedTest(string $name, array $properties = []): self
    {
        if ($name !== '') {
            $properties['Name'] = $name;
        }

        $this->createTest(self::FAILED, $properties);
        return $this;
    }

    public function createPassedTest(string $name, array $properties = []): self
    {
        if ($name !== '') {
            $properties['Name'] = $name;
        }

        $this->createTest(self::PASSED, $properties);
        return $this;
    }

    protected function createTest(string $status, array $properties): self
    {
        $faker = $this->getFaker();
        $properties['Status'] = $status;

        if (!isset($properties['Name'])) {
            $properties['Name'] = $faker->word;
        }

        if (!isset($properties['Path'])) {
            $properties['Path'] = "{$this->working_directory}/src";
        }

        if (!isset($properties['FullName'])) {
            $properties['FullName'] = "{$properties['Path']}/{$properties['Name']}";
        }

        if (!isset($properties['FullCommandLine'])) {
            $properties['FullCommandLine'] = "{$this->checker} {$properties['FullName']}";
        }

        if (!isset($properties['Log'])) {
            $properties['LogCompression'] = 'gzip';
            $properties['LogEncoding'] = 'base64';
            $properties['Log'] = base64_encode(gzencode($faker->text(), 9));
        }

        $this->createResults($properties);
        $this->setModel('DynamicAnalysis', $properties);
        return $this;
    }

    private function createResults(array &$properties): void
    {
        if (!isset($properties['Results'])) {
            $properties['Results'] = [];
            $posibilities = [
                'IPW',
                'Memory Leak',
                'Potential Memory Leak',
                'Uninitialized Memory Conditional',
                'Uninitialized Memory Read',
            ];
            $faker = $this->getFaker();
            $num_defects = $faker->randomDigit;
            for ($i = 0; $i < $num_defects; $i++) {
                $random = rand(0, 4);
                $properties['Results'][] = [
                    'type' => $posibilities[$random],
                    'value' => $faker->randomNumber(1),
                ];
            }
        }
    }
}
