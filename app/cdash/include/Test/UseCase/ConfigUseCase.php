<?php
namespace CDash\Test\UseCase;

use DOMDocument;
use DOMElement;
use DOMText;
use ConfigureHandler;

class ConfigUseCase extends UseCase
{
    public function __construct(array $properties = [])
    {
        parent::__construct('Config', $properties);
    }

    public function build()
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $startDateTimeText = date('M d H:i T', $this->startTime);
        $endDateTimeText = date('M d H:i T', $this->endTime);
        $site = $xml->appendChild($this->getSiteElement($xml));
        $configure = $site->appendChild(new DOMElement('Configure'));

        $startDateTime = $configure->appendChild(new DOMElement('StartDateTime'));
        $startTestTime = $configure->appendChild(new DOMElement('StartTestTime'));

        $startDateTime->appendChild(new DOMText($startDateTimeText));
        $startTestTime->appendChild(new DOMText($this->startTime));

        $command = $configure->appendChild(new DOMElement('ConfigureCommand'));

        if (isset($this->properties['Config']['command'])) {
            $command->appendChild(new DOMText($this->properties['Config']['command']));
        }

        $status = $configure->appendChild(new DOMElement('ConfigureStatus'));

        if (isset($this->properties['Config']['status'])) {
            $status->appendChild(new DOMText($this->properties['Config']['status']));
        }

        $log = $configure->appendChild(new DOMElement('Log'));

        if (isset($this->properties['Config']['log'])) {
            $log->appendChild(new DOMText($this->properties['Config']['log']));
        }

        $elapsed = $configure->appendChild(new DOMElement('ElapsedMinutes'));

        if (isset($this->properties['Config']['elapsed'])) {
            $elapsed->appendChild(new DOMText($this->properties['Config']['elapsed']));
        }

        $endDateTime = $configure->appendChild(new DOMElement('EndDateTime'));
        $endTestTime = $configure->appendChild(new DOMElement('EndTestTime'));

        $endDateTime->appendChild(new DOMText($endDateTimeText));
        $endTestTime->appendChild(new DOMText($this->endTime));

        $xml_str = $xml->saveXML($xml);
        $handler = new ConfigureHandler($this->projectId);
        return $this->getXmlHandler($handler, $xml_str);
    }
}
