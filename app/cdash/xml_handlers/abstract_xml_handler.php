<?php

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
use App\Utils\Stack;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\ServiceContainer;
use Illuminate\Support\Facades\Storage;
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
    protected $SubProjectName;

    private $ModelFactory;

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

        // let us control the failures so we can continue
        // parsing files instead of crashing midway
        libxml_use_internal_errors(true);

        // load the input file to be validated
        $local_path = '';
        $xml = new DOMDocument();
        if (file_exists($path)) {
            $xml->load($path, LIBXML_PARSEHUGE);
        } else {
            if (!Storage::exists($path)) {
                throw new FileNotFoundException($path);
            }
            if (config('filesystem.default') === 'local') {
                $xml->load(Storage::path($path), LIBXML_PARSEHUGE);
            } else {
                // Temporarily download the file because DOMDocument->load takes a path,
                // not a stream...
                $fp = Storage::readStream($path);
                if ($fp === null) {
                    throw UnableToReadFile::fromLocation($path);
                }
                $local_path = 'tmp/' . basename($path);
                Storage::disk('local')->put($local_path, $fp);
                $xml->load(Storage::disk('local')->path($local_path), LIBXML_PARSEHUGE);
            }
        }

        // run the validator and collect errors if there are any.
        $errors = [];
        if (!$xml->schemaValidate(base_path(static::$schema_file))) {
            $validation_errors = libxml_get_errors();
            foreach ($validation_errors as $error) {
                if ($error->level === LIBXML_ERR_ERROR || $error->level === LIBXML_ERR_FATAL) {
                    $errors[] = "WARNING: {$error->message} in {$error->file}, line: {$error->line}, column: {$error->column}";
                }
            }
            libxml_clear_errors();
        }

        if (config('filesystem.default') !== 'local') {
            Storage::disk('local')->delete($local_path);
        }

        return $errors;
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
            } else {
                return false;
            }
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

        if ($name == 'SUBPROJECT') {
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

    public function getBuildStamp()
    {
        return $this->Build->GetStamp();
    }

    public function getBuildName()
    {
        return $this->Build->Name;
    }

    public function getSubProjectName()
    {
        return $this->Build->SubProjectName;
    }

    protected function getModelFactory(): ServiceContainer
    {
        if (!$this->ModelFactory) {
            $this->ModelFactory = ServiceContainer::getInstance();
        }
        return $this->ModelFactory;
    }

    public function GetProject(): Project
    {
        $this->Project->Fill();
        return $this->Project;
    }

    public function GetSite(): Site
    {
        return $this->Site;
    }
}
