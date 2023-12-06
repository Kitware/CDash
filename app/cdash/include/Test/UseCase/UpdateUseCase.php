<?php
namespace CDash\Test\UseCase;

class UpdateUseCase extends UseCase
{
    public const TYPE = 'Update';
    public const FAILED = 'FAILED';

    private $mode;
    private $generator;

    public function __construct(array $properties = [])
    {
        parent::__construct(self::UPDATE, $properties);
        $this->mode = 'Client';
        $this->generator = 'ctest-2.8.4.20110707-g0eecf';
    }

    public function build(): \AbstractHandler
    {
        $prop = $this->properties[self::UPDATE];

        $xml = new \DOMDocument('1.0', 'UTF-8');
        $startDateTimeText = date('M d H:i T', $this->startTime);
        $endDateTimeText = date('M d H:i T', $this->endTime);

        // create update root element
        $update = $xml->appendChild(new \DOMElement('Update'));
        /** @var \DOMElement $update */
        $update->setAttribute('mode', $this->mode);
        $update->setAttribute('Generator', $this->generator);

        // create Site element
        $site = $update->appendChild(new \DOMElement('Site'));
        $site->appendChild(new \DOMText($this->properties['Site'][0]['Name']));

        // create BuildName element
        $buildName = $update->appendChild(new \DOMElement('BuildName'));
        $buildName->appendChild(new \DOMText($prop['BuildName']));

        // create BuildStamp element
        $buildStamp = $update->appendChild(new \DOMElement('BuildStamp'));
        $buildStamp->appendChild(new \DOMText($prop['BuildStamp']));

        // create StartDateTime element
        $startText = $update->appendChild(new \DOMElement('StartDateTime'));
        $startText->appendChild(new \DOMText($startDateTimeText));

        // create StartTime element
        $startTime = $update->appendChild(new \DOMElement('StartTime'));
        $startTime->appendChild(new \DOMText($this->startTime));

        // create Revision element
        $revision = $update->appendChild(new \DOMElement('Revision'));
        $revision->appendChild(new \DOMText($prop['Revision']));

        // create PriorRevision element
        $priorRevision = $update->appendChild(new \DOMElement('PriorRevision'));
        $priorRevision->appendChild(new \DOMText($prop['PriorRevision']));

        if (isset($prop['Directory'])) {
            $this->createDirectoryElement($update, $prop['Directory']);
        }

        // create EndDateTime element
        $endText = $update->appendChild(new \DOMElement('EndDateTime'));
        $endText->appendChild(new \DOMText($endDateTimeText));

        // create EndTime element
        $endTime = $update->appendChild(new \DOMElement('EndTime'));
        $endTime->appendChild(new \DOMText($this->endTime));

        // create ElapsedMinutes element
        $minutes = ($this->endTime - $this->startTime) / 60;
        $elapsed = $update->appendChild(new \DOMElement('ElapsedMinutes'));
        $elapsed->appendChild(new \DOMText($minutes));

        // create UpdateReturnStatus element
        $status = $update->appendChild(new \DOMElement('UpdateReturnStatus'));
        $text = $prop['UpdateReturnStatus'] ?? '';
        $status->appendChild(new \DOMText($text));

        $xml_str = $xml->saveXML($xml);
        if ($xml_str === false) {
            throw new \Exception('Invalid XML.');
        }
        $handler = new \UpdateHandler($this->projectId);
        return $this->getXmlHandler($handler, $xml_str);
    }

    protected function createDirectoryElement(\DOMElement $root, array $directories): void
    {
        foreach ($directories as $dir => $packages) {
            $directory = $root->appendChild(new \DOMElement('Directory'));

            // create Name element
            $name = $directory->appendChild(new \DOMElement('Name'));
            $name->appendChild(new \DOMText($dir));

            // create Updated elements
            foreach ($packages as $pkg) {
                $updated = $directory->appendChild(new \DOMElement('Updated'));

                // create File element
                $file = $updated->appendChild(new \DOMElement('File'));
                $file->appendChild(new \DOMText($pkg['File']));

                // create Directory element
                $d = $updated->appendChild(new \DOMElement('Directory'));
                $d->appendChild(new \DOMText($dir));

                // create FullName element
                $fullpath = "{$dir}/{$pkg['File']}";
                $fullName = $updated->appendChild(new \DOMElement('FullName'));
                $fullName->appendChild(new \DOMText($fullpath));

                // create CheckinDate element
                $checkin = $updated->appendChild(new \DOMElement('CheckinDate'));
                $checkin->appendChild(new \DOMText($pkg['CheckinDate']));

                // create Author element
                $author = $updated->appendChild(new \DOMElement('Author'));
                $author->appendChild(new \DOMText($pkg['Author']));

                // create Email element
                $email = $updated->appendChild(new \DOMElement('Email'));
                $email->appendChild(new \DOMText($pkg['Email']));

                // create Committer element
                $committer = $updated->appendChild(new \DOMElement('Committer'));
                $committer->appendChild(new \DOMText($pkg['Committer']));

                // create CommitterEmail element
                $committerEmail = $updated->appendChild(new \DOMElement('CommitterEmail'));
                $committerEmail->appendChild(new \DOMText($pkg['CommitterEmail']));

                // create CommitDate element
                // TODO: refactor to incldue forgotten CommitDate

                // create Log element
                $log = $updated->appendChild(new \DOMElement('Log'));
                $log->appendChild(new \DOMText($pkg['Log']));

                // create Revision element
                $revision = $updated->appendChild(new \DOMElement('Revision'));
                $revision->appendChild(new \DOMText($pkg['Revision']));

                // create PriorRevision element
                $priorRevision = $updated->appendChild(new \DOMElement('PriorRevision'));
                $priorRevision->appendChild(new \DOMText($pkg['PriorRevision']));
            }
        }
    }


    protected function set($property, $value): self
    {
        $this->properties[self::UPDATE][$property] = $value;
        return $this;
    }

    public function setBuildName(string $name): self
    {
        return $this->set('BuildName', $name);
    }

    public function setBuildType(string $type): self
    {
        $today = date('Ymd');
        $stamp = "{$today}-0000-{$type}";
        return $this->set('BuildStamp', $stamp);
    }

    public function setUpdateCommand(string $command): self
    {
        return $this->set('UpdateCommand', $command);
    }

    public function setRevision(string $revision): self
    {
        // this can be done automatically if not called
        return $this->set('Revision', $revision);
    }

    public function setPriorRevision(string $revision): self
    {
        // this can be done automatically if not called
        return $this->set('PriorRevision', $revision);
    }

    public function setPackages(array $packages): self
    {
        $directories = [];
        foreach ($packages as $package) {
            $key = $package['Directory'];
            if (!isset($directories[$key])) {
                $directories[$package['Directory']] = [];
            }
            $directories[$key][] = $package;
        }
        return $this->set('Directory', $directories);
    }

    /**
     * Checks if an array is associative, sequential or a mixture of both. Will return true
     * only if all array keys are ints.
     *
     * @ref https://gist.github.com/Thinkscape/1965669
     */
    private function isSequential(array $array): bool
    {
        return $array === array_values($array);
    }

    public function createPackage(array $properties): array
    {
        if ($this->isSequential($properties)) {
            [$name, $file, $author] = $properties;
            $properties = [
                'Name' => $name,
                'File' => $file,
                'Author' => $author,
            ];
        }

        // ensure that required properties are set
        if (!isset($properties['Name'])) {
            throw new \Exception("A 'Name' property must be present to create a package");
        }

        if (!isset($properties['File'])) {
            throw new \Exception("A 'File' property must be present to create a package");
        }

        if (!isset($properties['Author'])) {
            throw new \Exception("An 'Author' property must be present to create a package");
        }

        // create some reasonable defaults based on the name
        if (!isset($properties['Directory'])) {
            $properties['Directory'] = "packages/path/to/{$properties['Name']}";
        }

        if (!isset($properties['Email'])) {
            $properties['Email'] = $this->createEmail($properties['Author']);
        }

        if (!isset($properties['Committer'])) {
            $properties['Committer'] = $properties['Author'];
        }

        if (!isset($properties['CommitterEmail'])) {
            $properties['CommitterEmail'] = $this->createEmail($properties['Committer']);
        }

        if (!isset($properties['Revision'])) {
            $properties['Revision'] = $this->createRevisionHash();
        }

        if (!isset($properties['PriorRevision'])) {
            $properties['PriorRevision'] = $this->createRevisionHash();
        }

        if (!isset($properties['Log'])) {
            $properties['Log'] = "feat: adding feature to {$properties['Name']}";
        }

        if (!isset($properties['CheckinDate'])) {
            $properties['CheckinDate'] = $this->randomizeCheckinDate();
        }

        return $properties;
    }

    public function createRevisionHash(): string
    {
        return sha1(uniqid('_package_', true));
    }

    public function randomizeCheckinDate(): string
    {
        $random = rand(1, 9*60*60) + (9*60*60); // seconds between 8am and 5pm
        $time = strtotime("yesterday +{$random} seconds");
        return date('Y-m-d H:i:s -0500', $time);
    }

    protected function createEmail(string $author): string
    {
        $names = explode(" ", $author);
        $first = preg_replace('/\W/', '', $names[0]);
        $last = count($names) > 1 ? array_pop($names) : null;
        $last = $last ? ('.' . preg_replace('/\W*/', '', $last)) : '';
        $site = $this->properties['Site'][0]['Name'];
        $email = "{$first}{$last}@{$site}";
        return strtolower($email);
    }
}
