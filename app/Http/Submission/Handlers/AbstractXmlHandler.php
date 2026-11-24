<?php

namespace App\Http\Submission\Handlers;

/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

use App\Models\Site;
use App\Services\XmlValidationService;
use App\Utils\Stack;
use CDash\Model\Build;
use CDash\Model\Project;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

abstract class AbstractXmlHandler extends AbstractSubmissionHandler
{
    /**
     * @var Stack<string>
     */
    private Stack $stack;
    protected bool $Append = false;
    protected Site $Site;
    protected string $SubProjectName = '';

    protected static ?string $schema_file = null;

    public function __construct(Build|Project $init)
    {
        parent::__construct($init);

        $this->stack = new Stack();
    }

    /**
     * Validate the given XML file based on its type
     *
     * @return array<string>
     *
     * @throws FileNotFoundException
     * @throws UnableToReadFile
     */
    public static function validate(string $path): array
    {
        if (static::$schema_file === null) {
            return [];
        }

        return XmlValidationService::validate($path, base_path(static::$schema_file));
    }

    protected function getParent(): ?string
    {
        if ($this->stack->size() <= 1) {
            return null;
        }

        return $this->stack->at($this->stack->size() - 2);
    }

    protected function currentPathMatches(string $path): bool
    {
        $path = explode('.', $path);

        // We can return early if this isn't even the right level of the document
        if ($this->stack->size() !== count($path)) {
            return false;
        }

        for ($i = 0; $i < $this->stack->size(); $i++) {
            if ($path[$i] === '*') {  // Wildcard matches any string at a given level (but only one level)
                continue;
            } elseif (strtoupper($path[$i]) === strtoupper((string) $this->stack->at($i))) {  // Match the specified string
                continue;
            }
            return false;
        }
        return true;
    }

    protected function getElement()
    {
        return $this->stack->top();
    }

    public function startElement($parser, $name, $attributes): void
    {
        $this->stack->push($name);

        if ($name === 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
        }

        if (array_key_exists('APPEND', $attributes) && strtolower($attributes['APPEND']) == 'true') {
            $this->Append = true;
        }
    }

    public function endElement($parser, $name): void
    {
        $this->stack->pop();
    }

    abstract public function text($parser, $data);

    public function getSiteName(): string
    {
        return $this->Site->name;
    }

    public function getBuildStamp(): string
    {
        return $this->Build->GetStamp();
    }

    public function getBuildName(): string
    {
        return $this->Build->Name;
    }

    public function getSubProjectName()
    {
        return $this->Build->SubProjectName;
    }

    public function GetSite(): Site
    {
        return $this->Site;
    }
}
