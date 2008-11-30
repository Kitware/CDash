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
include("config.php");
require_once("pdo.php");
include('login.php');
include("version.php");
include("models/project.php");
include("models/site.php");
include("models/user.php");
include("models/image.php");
include("models/build.php");

if($session_OK) 
{
include_once('common.php');

set_time_limit(0);

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

checkUserPolicy(@$_SESSION['cdash']['loginid'],0); // only admin
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<title>CDash - Import Backups</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Import Backup</menusubtitle>";
$xml .= "<backurl>manageBackup.php</backurl>";
$xml .= "</cdash>";


// Basic error functions
class errorBase
{
  function backtrace($provideObject=false)
    {
    foreach(debug_backtrace($provideObject) as $row)
      {
      if($last!=$row['file'])
                echo "File: $file<br>\n'";
      $last=$row['file'];
            echo " Line: $row[line]: ";
            if($row['class']!='')
                echo "$row[class]$row[type]$row[function]";
            else
                echo "$row[function]";
            echo "(";
            echo join("', '",$args);
            echo ")<br>\n";
        }
    }
    function error($msg,$fatal=false)
    {
        echo "<div align=\"center\"><font color=\"red\"><b>Error: $msg</b></font></div>";
        $last='';
        //$this->backtrace();
        exit();
    }
    function fatalError($msg){return $this->error($msg,true);}
}

// Basic XML parser with all call-backs abstracted
class xmlBase  extends errorBase
{

    // Valuable resource
    var $_p;
    var $Filename;

    // Constructor/destructor
    function xmlBase($ns=false,$encoding=null,$separator=null)
    {
            $this->_p = $ns
                                                ?
                                                xml_parser_create_ns($encoding,$separator)
                                                :
                                                xml_parser_create($encoding);

            xml_set_object($this->_p, $this);
            xml_set_default_handler($this->_p,"_default");
            xml_set_element_handler($this->_p, "_tagOpen", "_tagClose");
            xml_set_character_data_handler($this->_p, "_cdata");
            xml_set_start_namespace_decl_handler($this->_p,"_nsStart");
            xml_set_end_namespace_decl_handler($this->_p,"_nsEnd");
            xml_set_external_entity_ref_handler($this->_p,"_entityRef");
            xml_set_processing_instruction_handler($this->_p,"_pi");
            xml_set_notation_decl_handler($this->_p,"_notation");
            xml_set_unparsed_entity_decl_handler($this->_p,"_unparsedEntity");
    }
    function _xmlBase()                                                {xml_parser_free($this->_p);$this->_p=null;}
   
    // All private abstract methods - override these
    function _default($parser,$data)            {}
    function _tagOpen($parser,$tag,
                                                                                        $attribs){}
    function _tagClose($parser,$tag)        {}
    function _cdata($parser,$data)            {}
    function _nsStart($parser,
                                                            $userData,
                                                            $prefix,$uri)                {}
    function _nsEnd($parser,$userData,
                                                        $prefix)                                {}
    function _entityRef($parser,
                                  $openEntityNames,
                                                                $base,
                                                                $systemID,
                                                                $publicID)                    {}
    function _pi($parser,$target,$data){}
    function _notation($parser,
                                  $notationName,
                                                                $base,
                                                                $systemID,
                                                                $publicID)                    {}
    function _unparsedEntity($parser,
                                  $entityName,
                                                                $base,
                                                                $systemID,
                                                                $publicID,
                                                                $notationName){}

    function _parse($data,$final=false)
    {
        if(!xml_parse($this->_p,$data,$final))
        {
            echo $data.'<br />';
            $this->fatalError(sprintf('XML error %d:"%s" at line %d column %d byte %d',
            xml_get_error_code($this->_p),
            xml_error_string($this->_p),
            xml_get_current_line_number($this->_p),
            xml_get_current_column_number($this->_p),
            xml_get_current_byte_index($this->_p)));
        }
    }
   
    // All the public functions you're meant to not override
    function setOption($option,$value)    {return xml_parser_set_option($this->_p,$option,$value);}
    function getOption($option)              {return xml_parser_get_option($this->_p,$option);}
    function parseFile($file)
    {
       $this->Filename = $file;
       
        if(($f=fopen($file,'r'))!=null)
        {
            while(!feof($f))
                $this->parse(fgets($f,1024));
            $this->parseEnd();
        }
        else
            $this->fatalError("Unable to open file $file");
    }
    function parseEnd()
    {
        $this->_parse(null,true);
    }
    function parse($data)
    {
        $this->_parse($data);
    }
}
    
// Basic XML parser with all call-backs abstracted
class xmlImporter extends xmlBase
{
  var $CurrentObject;
  
  var $CurrentSubObject;
  var $CurrentTag;

  var $CurrentProject;
  var $CurrentBuildGroup;
  var $CurrentBuildGroupPosition;
  var $CurrentBuildGroupRule;
  var $CurrentSite;
  var $CurrentSiteInformation;
  var $CurrentImage;
  var $CurrentUser;
  var $CurrentUserProject;
  var $CurrentBuild;
  var $CurrentBuildNote;
  var $CurrentNote;
  var $CurrentBuildUpdate;
  var $CurrentBuildUpdateFiles;
  var $CurrentBuildConfigure;
  var $CurrentBuildConfigureError;
  var $CurrentBuildConfigureErrorDiff;
  var $CurrentBuildError;
  var $CurrentBuildErrorDiff;
  var $CurrentBuildTest;
  var $CurrentTest;
  var $CurrentTestImage;
  var $CurrentTestMeasurement;
  var $CurrentBuildTestDiff;
  var $CurrentDailyUpdate;
  var $CurrentDailyUpdateFile;
  
  // Should we skip the current tag
  var $SkipBuild;
      
  function _constructor()
    {
    $this->CurrentObject = "";
    $this->CurrentSubObject = "";
    $this->CurrentTag = "";
    $this->SkipBuild = 0;
    }
  
  
  /** */
  function _default($parser,$data)
    {
    }
  /** */   
  function _tagOpen($parser,$tag,$attribs)
    {
    if($tag == "PROJECT" && $this->CurrentObject=="")
      {
      $this->CurrentObject = "project";
      $this->CurrentProject = new Project;
      $this->CurrentProject->Id = $attribs['ID'];
      }
    else if($tag == "BUILDGROUP" && $this->CurrentObject=="project")
      {
      $this->CurrentSubObject = "buildgroup";
      $this->CurrentBuildGroup = new BuildGroup;
      $this->CurrentBuildGroup->Id = $attribs['ID'];
      }
    else if($tag == "POSITION" && $this->CurrentSubObject=="buildgroup")
      {
      $this->CurrentSubObject = "buildgroupposition";
      $this->CurrentBuildGroupPosition = new BuildGroupPosition;
      $this->CurrentBuildGroupPosition->Position = $attribs['ID'];
      }
    else if($tag == "RULE" && $this->CurrentSubObject=="buildgroup")
      {
      $this->CurrentSubObject = "buildgrouprule";
      $this->CurrentBuildGroupRule = new BuildGroupRule;
      }
    else if($tag == "DAILYUPDATE" && $this->CurrentObject=="project")
      {
      $this->CurrentSubObject = "dailyupdate";
      $this->CurrentDailyUpdate = new DailyUpdate;
      $this->CurrentDailyUpdate->Id = $attribs['ID'];
      }
    else if($tag == "DAILYUPDATEFILE" && $this->CurrentSubObject=="dailyupdate")
      {
      $this->CurrentSubObject = "dailyupdatefile";
      $this->CurrentDailyUpdateFile = new DailyUpdateFile;
      }
    else if($tag == "SITE" && $this->CurrentObject=="")
      {
      $this->CurrentObject = "site";
      $this->CurrentSite = new Site;
      $this->CurrentSite->Id = $attribs['ID'];
      }
    else if($tag == "SITEINFORMATION" && $this->CurrentObject=="site")
      {
      $this->CurrentSubObject = "siteinformation";
      $this->CurrentSiteInformation = new SiteInformation;
      }
    else if($tag == "IMAGE" && $this->CurrentObject=="")
      {
      $this->CurrentObject = "image";
      $this->CurrentImage = new Image;
      $this->CurrentImage->Id = $attribs['ID'];
      }
    else if($tag == "USER" && $this->CurrentObject=="")
      {
      $this->CurrentObject = "user";
      $this->CurrentUser = new User;
      $this->CurrentUser->Id = $attribs['ID'];
      }
    else if($tag == "SITE" && $this->CurrentObject=="user")
      {
      $this->CurrentSubObject = "site";
      $this->CurrentUser->AddSite($attribs['ID']);
      }
    else if($tag == "PROJECT" && $this->CurrentObject=="user")
      {
      $this->CurrentSubObject = "project";
      $this->CurrentUserProject = new UserProject;
      $this->CurrentUserProject->ProjectId = $attribs['ID'];
      }
    else if($tag == "BUILD" && $this->CurrentObject=="")
      {
      $this->CurrentObject = "build";
      $this->CurrentBuild = new Build;
      $this->CurrentBuild->Id = $attribs['ID'];
      if($this->CurrentBuild->Exists())
        {
        $this->SkipBuild = 1;
        }
      
      }
    else if($tag == "BUILDNOTE" && $this->CurrentObject=="build")
      {
      $this->CurrentSubObject = "buildnote";
      $this->CurrentBuildNote = new BuildNote;
      }
    else if($tag == "NOTE" && $this->CurrentObject=="build")
      {
      $this->CurrentSubObject = "note";
      $this->CurrentNote = new Note;
      $this->CurrentNote->Id = $attribs['ID'];
      }
    else if($tag == "UPDATE" && $this->CurrentObject=="build")
      {
      $this->CurrentSubObject = "update";
      $this->CurrentBuildUpdate = new BuildUpdate;
      $this->CurrentBuildUpdate->BuildId = $this->CurrentBuild->Id;
      }
    else if($tag == "FILE" && $this->CurrentObject=="build" && ($this->CurrentSubObject=="update" || $this->CurrentSubObject=="updatefile"))
      {
      $this->CurrentSubObject = "updatefile";
      $this->CurrentBuildUpdateFiles = new BuildUpdateFiles;
      }
    else if($tag == "CONFIGURE" && $this->CurrentObject=="build")
      {
      $this->CurrentSubObject = "configure";
      $this->CurrentBuildConfigure = new BuildConfigure;
      $this->CurrentBuildConfigure->BuildId = $this->CurrentBuild->Id;
      }
    else if($tag == "ERROR" && $this->CurrentObject=="build" && ($this->CurrentSubObject=="configure" || $this->CurrentSubObject=="configureerror"))
      {
      $this->CurrentSubObject = "configureerror";
      $this->CurrentBuildConfigureError = new BuildConfigureError;
      }    
    else if($tag == "ERRORDIFF" && $this->CurrentObject=="build" && ($this->CurrentSubObject=="configure" || $this->CurrentSubObject=="configureerrordiff"))
      {
      $this->CurrentSubObject = "configureerrordiff";
      $this->CurrentConfigureErrorDiff = new BuildConfigureErrorDiff;
      $this->CurrentBuildConfigureErrorDiff->Type = $attribs['TYPE'];
      }
    else if($tag == "BUILDERROR" && $this->CurrentObject=="build")
      {
      $this->CurrentSubObject = "builderror";
      $this->CurrentBuildError = new BuildError;
      }
    else if($tag == "BUILDERRORDIFF" && $this->CurrentObject=="build")
      {
      $this->CurrentSubObject = "builderrordiff";
      $this->CurrentBuildErrorDiff = new BuildErrorDiff;
      $this->CurrentBuildConfigureErrorDiff->Type = $attribs['TYPE'];
      }
    else if($tag == "BUILDTEST" && $this->CurrentObject=="build")
      {
      $this->CurrentSubObject = "buildtest";
      $this->CurrentBuildTest = new BuildTest;
      $this->CurrentBuildTest->BuildId = $this->CurrentBuild->Id;
      }
    else if($tag == "TEST" && $this->CurrentObject=="build"  && ($this->CurrentSubObject=="buildtest" || $this->CurrentSubObject=="test"))
      {
      $this->CurrentSubObject = "test";
      $this->CurrentTest = new Test;
      }
    else if($tag == "IMAGE" && $this->CurrentObject=="build"  && ($this->CurrentSubObject=="test" || $this->CurrentSubObject=="testimage"))
      {
      $this->CurrentSubObject = "testimage";
      $this->CurrentTestImage = new TestImage;
      $this->CurrentTestImage->Id = $attribs['ID'];
      }    
    else if($tag == "MEASUREMENT" && $this->CurrentObject=="build" && ($this->CurrentSubObject=="test" || $this->CurrentSubObject=="testmeasurement"))
      {
      $this->CurrentSubObject = "testmeasurement";
      $this->CurrentTestMeasurement = new TestMeasurement;
      }  
    else if($tag == "TESTDIFF" && $this->CurrentObject=="build" && ($this->CurrentSubObject=="test" || $this->CurrentSubObject=="testdiff"))
      {
      $this->CurrentSubObject = "testdiff";
      $this->CurrentBuildTestDiff = new BuildTestDiff;
      $this->CurrentBuildTestDiff->BuildId = $this->CurrentBuild->Id;
      }  

    $this->CurrentTag = $tag;
      
    /*echo "OPEN: ".$tag;
    print_r($attribs);
    echo "<br>";*/
    }
  /** */
  function _tagClose($parser,$tag)
    {
    if($tag == "PROJECT" && $this->CurrentObject=="project")
      {
      $this->CurrentObject = "";
      $this->Projects[] = $this->CurrentProject;
      $this->CurrentProject->Save();
      }
    else if($tag == "BUILDGROUP" && $this->CurrentSubObject=="buildgroup")
      {
      $this->CurrentSubObject = "";
      $this->CurrentProject->AddBuildGroup($this->CurrentBuildGroup);
      }
    else if($tag == "POSITION" && $this->CurrentSubObject=="buildgroupposition")
      {
      $this->CurrentSubObject="buildgroup";
      $this->CurrentBuildGroup->SetPosition($this->CurrentBuildGroupPosition);
      }
    else if($tag == "RULE" && $this->CurrentSubObject=="buildgrouprule")
      {
      $this->CurrentSubObject="buildgroup";
      $this->CurrentBuildGroup->AddRule($this->CurrentBuildGroupRule);
      }
    else if($tag == "DAILYUPDATE" && $this->CurrentSubObject=="dailyupdate")
      {
      $this->CurrentSubObject = "";
      $this->CurrentProject->AddDailyUpdate($this->CurrentDailyUpdate);
      }
    else if($tag == "DAILYUPDATEFILE" && $this->CurrentSubObject=="dailyupdatefile")
      {
      $this->CurrentSubObject = "dailyupdate";
      $this->CurrentDailyUpdate->AddFile($this->CurrentDailyUpdateFile);
      }    
    else if($tag == "SITE" && $this->CurrentObject=="site")
      {
      $this->CurrentObject = "";
      $this->CurrentSite->Save();
      }
    else if($tag == "SITEINFORMATION" && $this->CurrentObject=="site")
      {
      $this->CurrentSubObject = "";
      $this->CurrentSite->SetInformation($this->CurrentSiteInformation);
      }  
    else if($tag == "IMAGE" && $this->CurrentObject=="image")
      {
      $this->CurrentObject = "";
      $this->CurrentImage->Filename = dirname($this->Filename)."/".$this->CurrentImage->Filename;
      $this->CurrentImage->Save();
      }
    else if($tag == "USER" && $this->CurrentObject=="user")
      {
      $this->CurrentObject = "";
      $this->CurrentUser->Save();
      }
    else if($tag == "PROJECT" && $this->CurrentObject=="user" && $this->CurrentSubObject=="project")
      {
      $this->CurrentSubObject = "";
      $this->CurrentUser->Addproject($this->CurrentUserProject);      
      }
    else if($tag == "BUILD" && $this->CurrentObject=="build")
      {
      if(!$this->SkipBuild)
        {
        $this->CurrentObject = "";
        $this->CurrentBuild->Save();
        }
      $this->SkipBuild = 0;  
      }
    else if($tag == "BUILDNOTE" && $this->CurrentObject=="build" && $this->CurrentSubObject=="buildnote" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "";
      $this->CurrentBuild->SaveBuildNote($this->CurrentBuildNote);
      }
    else if($tag == "NOTE" && $this->CurrentObject=="build" && $this->CurrentSubObject=="note" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "";
      $this->CurrentBuild->SaveNote($this->CurrentNote);
      }
   else if($tag == "UPDATE" && $this->CurrentObject=="build" && $this->CurrentSubObject=="update" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "";
      $this->CurrentBuild->SaveUpdate($this->CurrentBuildUpdate);
      }
    else if($tag == "FILE" && $this->CurrentObject=="build" && $this->CurrentSubObject=="updatefile" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "update";
      $this->CurrentBuildUpdate->AddFile($this->CurrentBuildUpdateFile);
      }
    else if($tag == "CONFIGURE" && $this->CurrentObject=="build" && $this->CurrentSubObject=="configure" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "";
      $this->CurrentBuild->SaveConfigure($this->CurrentBuildConfigure);
      }
    else if($tag == "ERROR" && $this->CurrentObject=="build" && $this->CurrentSubObject=="configureerror" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "configure";
      $this->CurrentBuildConfigure->AddError($this->CurrentBuildConfigureError);
      }    
    else if($tag == "ERRORDIFF" && $this->CurrentObject=="build" && $this->CurrentSubObject=="configureerrordiff" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "configure";
      $this->CurrentBuildConfigure->AddErrorDiff($this->CurrentConfigureErrorDiff);
      }
    else if($tag == "BUILDERROR" && $this->CurrentObject=="build" && $this->CurrentSubObject=="builderror" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "";
      $this->CurrentBuild->AddError($this->CurrentBuildError);
      }
    else if($tag == "BUILDERRORDIFF" && $this->CurrentObject=="build" && $this->CurrentSubObject=="configureerrordiff" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "";
      $this->CurrentBuild->AddErrorDiff($this->CurrentBuildErrorDiff);
      }  
   else if($tag == "BUILDTEST" && $this->CurrentObject=="build" && $this->CurrentSubObject=="buildtest" && !$this->SkipBuild)
     {
      $this->CurrentSubObject = "";
      $this->CurrentBuild->SaveTest($this->CurrentBuildTest);
      }
    else if($tag == "TEST" && $this->CurrentObject=="build" && $this->CurrentSubObject=="test" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "buildtest";
      $this->CurrentBuildTest->SetTest($this->CurrentTest);
      }
    else if($tag == "IMAGE" && $this->CurrentObject=="build" && $this->CurrentSubObject=="testimage" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "test";
      $this->CurrentTest->AddImage($this->CurrentTestImage);
      }    
    else if($tag == "MEASUREMENT" && $this->CurrentObject=="build" && $this->CurrentSubObject=="testmeasurement" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "test";
      $this->CurrentTest->AddMeasurement($this->CurrentTestMeasurement);
      }  
   else if($tag == "TESTDIFF" && $this->CurrentObject=="build" && $this->CurrentSubObject=="testdiff" && !$this->SkipBuild)
      {
      $this->CurrentSubObject = "test";
      $this->CurrentBuild->AddTestDiff($this->CurrentBuildTestDiff);
      }  

    $this->CurrentTag = "";  
    }
    
  /** */
  function _cdata($parser,$data)
    {
    if($this->CurrentObject == "project")
      {  
      if($this->CurrentSubObject=="buildgroup")
        {
        $this->CurrentBuildGroup->SetValue($this->CurrentTag,$data);
        }
      else if($this->CurrentSubObject=="buildgroupposition")
        {
        $this->CurrentBuildGroupPosition->SetValue($this->CurrentTag,$data);
        }
      else if($this->CurrentSubObject=="buildgrouprule")
        {
        $this->CurrentBuildGroupRule->SetValue($this->CurrentTag,$data);
        }
      else if($this->CurrentSubObject=="dailyupdate")
        {
        $this->CurrentDailyUpdate->SetValue($this->CurrentTag,$data);
        }
      else if($this->CurrentSubObject=="dailyupdatefile")
        {
        $this->CurrentDailyUpdateFile->SetValue($this->CurrentTag,$data);
        }
      else 
        {
        $this->CurrentProject->SetValue($this->CurrentTag,$data);
        }
      } // end project
    else if($this->CurrentObject == "site")
      {
      if($this->CurrentSubObject == "siteinformation")
        {
        $this->CurrentSiteInformation->SetValue($this->CurrentTag,$data);
        }
      else
        {
        $this->CurrentSite->SetValue($this->CurrentTag,$data);
        }
      }   // end site
    else if($this->CurrentObject == "image")
      {
      $this->CurrentImage->SetValue($this->CurrentTag,$data);
      } // end image
    else if($this->CurrentObject == "user")
      {
      if($this->CurrentSubObject == "project")
        {
        $this->CurrentUserProject->SetValue($this->CurrentTag,$data);
        }  
      else
        {
        $this->CurrentUser->SetValue($this->CurrentTag,$data);
        }
      } // end user
    else if($this->CurrentObject == "build")
      {
      if($this->CurrentSubObject == "buildnote")
        {
        $this->CurrentBuildNote->SetValue($this->CurrentTag,$data);
        }  
      else if($this->CurrentSubObject == "note")
        {
        $this->CurrentNote->SetValue($this->CurrentTag,$data);
        }  
      else if($this->CurrentSubObject == "update")
        {
        $this->CurrentBuildUpdate->SetValue($this->CurrentTag,$data);
        }  
      else if($this->CurrentSubObject == "updatefile")
        {
        $this->CurrentBuildUpdateFile->SetValue($this->CurrentTag,$data);
        }  
      else if($this->CurrentSubObject == "configure")
        {
        $this->CurrentBuildConfigure->SetValue($this->CurrentTag,$data);
        }  
      else if($this->CurrentSubObject == "configureerror")
        {
        $this->CurrentBuildConfigureError->SetValue($this->CurrentTag,$data);
        }  
      else if($this->CurrentSubObject == "configurediff")
        {
        $this->CurrentBuildConfigureErrorDiff->SetValue($this->CurrentTag,$data);
        }    
      else if($this->CurrentSubObject == "builderror")
        {
        $this->CurrentBuildError->SetValue($this->CurrentTag,$data);
        }    
     else if($this->CurrentSubObject == "builderrordiff")
        {
        $this->CurrentBuildErrorDiff->SetValue($this->CurrentTag,$data);
        }
     else if($this->CurrentSubObject == "buildtest")
        {
        $this->CurrentBuildTest->SetValue($this->CurrentTag,$data);
        }
      else if($this->CurrentSubObject == "test")
        {
        $this->CurrentTest->SetValue($this->CurrentTag,$data);
        }
      else if($this->CurrentSubObject == "testimage")
        {
        $this->CurrentTestImage->SetValue($this->CurrentTag,$data);
        }
      else if($this->CurrentSubObject == "testmeasurement")
        {
        $this->CurrentTestMeasurement->SetValue($this->CurrentTag,$data);
        }
      else if($this->CurrentSubObject == "testdiff")
        {
        $this->CurrentBuildTestDiff->SetValue($this->CurrentTag,$data);
        }
      else
        {
        $this->CurrentBuild->SetValue($this->CurrentTag,$data);
        }
      } // end build
      
      
    //echo "DATA: ".$data."<br>";
    }
  /** */
  function _nsStart($parser,$userData,$prefix,$uri)
    {
    }
  /** */
  function _nsEnd($parser,$userData,$prefix)
    {
    
    }
  /** */
  function _entityRef($parser,$openEntityNames,$base,$systemID,$publicID)
    {
    }
  /** */
  function _pi($parser,$target,$data)
    {
    }
  /** */
  function _notation($parser,$notationName,$base,$systemID,$publicID)
    {
    }
  /** */
  function _unparsedEntity($parser,$entityName,$base,$systemID,$publicID,$notationName)
    {
    }
}

/** Parse the XML file */
function parseBackupXML($filename)
{
  $xmlParser = new xmlImporter;
  $xmlParser->parseFile($filename);
  //print_r($xmlParser->Projects);
  //print_r($xmlParser->Images);
  //print_r($xmlParser->Sites);
  //print_r($xmlParser->Users);
  //print_r($xmlParser->Builds);
}

@$Submit = $_POST["Submit"];
if($Submit)
  {
  
  // clear the database (FOR TESTING ONLY)
  $tables = mysql_list_tables($CDASH_DB_NAME);
  $num_rows = mysql_num_rows($tables);
  for ($i = 0; $i < $num_rows; $i++) 
    {
    $table = mysql_tablename($tables, $i);
    if($table != "user")
      {
      pdo_query("TRUNCATE table $table");
      }
    }

  
  foreach(glob("$CDASH_BACKUP_DIRECTORY/database/*.xml") as $filename)
    {
    // Parse the XML file
    parseBackupXML($filename);
    }
  } // end submit

// Now doing the xslt transition
generate_XSLT($xml,"importExternalBackup");

} // end session
?>
