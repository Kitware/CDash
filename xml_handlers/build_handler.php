<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
require_once('xml_handlers/abstract_handler.php');
require_once('models/build.php');
require_once('models/label.php');
require_once('models/site.php');
require_once('models/buildfailure.php');
require_once('models/feed.php');

class BuildHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $Error;
    private $Label;
    private $Append;

    public function __construct($projectid, $scheduleid)
    {
        parent::__construct($projectid, $scheduleid);
        $this->Build = new Build();
        $this->Site = new Site();
        $this->Append = false;
        $this->Feed = new Feed();
    }

    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);

        if ($name=='SITE') {
            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = "(empty)";
            }
            $this->Site->Insert();

            $siteInformation = new SiteInformation();
            $buildInformation = new BuildInformation();

      // Fill in the attribute
      foreach ($attributes as $key=>$value) {
          if ($key === 'CHANGEID') {
              $this->Build->SetPullRequest($value);
              continue;
          }
          $siteInformation->SetValue($key, $value);
          $buildInformation->SetValue($key, $value);
      }

            $this->Site->SetInformation($siteInformation);

            $this->Build->SiteId = $this->Site->Id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = "(empty)";
            }
            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
            $this->Build->Information = $buildInformation;

            if (array_key_exists('APPEND', $attributes)) {
                if (strtolower($attributes['APPEND']) == "true") {
                    $this->Append = true;
                }
            } else {
                $this->Append = false;
            }
        } elseif ($name=='WARNING') {
            $this->Error = new BuildError();
            $this->Error->Type = 1;
        } elseif ($name=='ERROR') {
            $this->Error = new BuildError();
            $this->Error->Type = 0;
        } elseif ($name=='FAILURE') {
            $this->Error = new BuildFailure();
            $this->Error->Type = 0;
            if ($attributes['TYPE']=="Error") {
                $this->Error->Type = 0;
            } elseif ($attributes['TYPE']=="Warning") {
                $this->Error->Type = 1;
            }
        } elseif ($name == 'LABEL') {
            $this->Label = new Label();
        }
    }

    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);

        if ($name == 'BUILD') {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
            $submit_time = gmdate(FMT_DATETIME);

            $this->Build->ProjectId = $this->projectid;
            $this->Build->StartTime = $start_time;
            $this->Build->EndTime = $end_time;
            $this->Build->SubmitTime = $submit_time;
            $this->Build->SetSubProject($this->SubProjectName);
            $this->Build->Append = $this->Append;

            add_build($this->Build, $this->scheduleid);

            $this->Build->ComputeDifferences();

            global $CDASH_ENABLE_FEED;
            if ($CDASH_ENABLE_FEED) {
                // Insert the build into the feed
        $this->Feed->InsertBuild($this->projectid, $this->Build->Id);
            }
        } elseif ($name=='WARNING' || $name=='ERROR' || $name=='FAILURE') {
            global $CDASH_LARGE_TEXT_LIMIT;
            $threshold = $CDASH_LARGE_TEXT_LIMIT;

            if ($threshold > 0 && isset($this->Error->StdOutput)) {
                $outlen = strlen($this->Error->StdOutput);
                if ($outlen > $threshold) {
                    $tmp = substr($this->Error->StdOutput, 0, $threshold);
                    unset($this->Error->StdOutput);
                    $this->Error->StdOutput = $tmp .
            "\n...\nCDash truncated output because it exceeded $threshold characters.\n";
                    $outlen = strlen($this->Error->StdOutput);
          //add_log("truncated long out text", "build_handler", LOG_INFO);
                }

                $errlen = strlen($this->Error->StdError);
                if ($errlen > $threshold) {
                    $tmp = substr($this->Error->StdError, 0, $threshold);
                    unset($this->Error->StdError);
                    $this->Error->StdError = $tmp .
            "\n...\nCDash truncated output because it exceeded $threshold characters.\n";
                    $errlen = strlen($this->Error->StdError);
          //add_log("truncated long err text", "build_handler", LOG_INFO);
                }
            }

            $this->Build->AddError($this->Error);

            unset($this->Error);
        } elseif ($name == 'LABEL') {
            if (isset($this->Error)) {
                $this->Error->AddLabel($this->Label);
            } elseif (isset($this->Build)) {
                $this->Build->AddLabel($this->Label);
            }
        }
    }

    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();
        if ($parent == 'BUILD') {
            switch ($element) {
        case 'STARTBUILDTIME':
          $this->StartTimeStamp = $data;
          break;
        case 'STARTDATETIME':
          $this->StartTimeStamp = str_to_time($data, $this->Build->GetStamp());
          break;
        case 'ELAPSEDMINUTES':
          $this->EndTimeStamp = $this->StartTimeStamp+$data*60;
          break;
        case 'ENDDATETIME':
          $this->EndTimeStamp = $data;
          break;
        case 'BUILDCOMMAND':
          $this->Build->Command = $data;
          break;
        case 'LOG':
          $this->Build->Log .= $data;
          break;
        }
        } elseif ($parent == 'ACTION') {
            switch ($element) {
        case 'LANGUAGE':
          $this->Error->Language .= $data;
          break;
        case 'SOURCEFILE':
          $this->Error->SourceFile .= $data;
          break;
        case 'TARGETNAME':
          $this->Error->TargetName .= $data;
          break;
        case 'OUTPUTFILE':
          $this->Error->OutputFile .= $data;
          break;
        case 'OUTPUTTYPE':
          $this->Error->OutputType .= $data;
          break;
        }
        } elseif ($parent == 'COMMAND') {
            switch ($element) {
        case 'WORKINGDIRECTORY':
          $this->Error->WorkingDirectory .= $data;
          break;
        case 'ARGUMENT':
           $this->Error->AddArgument($data);
           break;
        }
        } elseif ($parent == 'RESULT') {
            global $CDASH_LARGE_TEXT_LIMIT;
            $threshold = $CDASH_LARGE_TEXT_LIMIT;
            $append = true;

            switch ($element) {
        case 'STDOUT':
          if ($threshold > 0) {
              if (strlen($this->Error->StdOutput) > $threshold) {
                  //add_log("avoiding append: long output", "build_handler", LOG_INFO);
              $append = false;
              }
          }

          if ($append) {
              $this->Error->StdOutput .= $data;
          }
          break;

        case 'STDERR':
          if ($threshold > 0) {
              if (strlen($this->Error->StdError) > $threshold) {
                  //add_log("avoiding append: long error", "build_handler", LOG_INFO);
              $append = false;
              }
          }

          if ($append) {
              $this->Error->StdError .= $data;
          }
          break;

        case 'EXITCONDITION':
          $this->Error->ExitCondition .= $data;
          break;
        }
        } elseif ($element == 'BUILDLOGLINE') {
            $this->Error->LogLine .= $data;
        } elseif ($element == 'TEXT') {
            $this->Error->Text .= $data;
        } elseif ($element == 'SOURCEFILE') {
            $this->Error->SourceFile .= $data;
        } elseif ($element == 'SOURCELINENUMBER') {
            $this->Error->SourceLine .= $data;
        } elseif ($element == 'PRECONTEXT') {
            $this->Error->PreContext .= $data;
        } elseif ($element == 'POSTCONTEXT') {
            $this->Error->PostContext .= $data;
        } elseif ($element == 'LABEL') {
            $this->Label->SetText($data);
        }
    } // end function text
} // end class;
