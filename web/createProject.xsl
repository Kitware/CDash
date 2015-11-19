<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  version='1.0'>

  <xsl:include href="footer.xsl" />
  <xsl:include href="headeradminproject.xsl" />
  <xsl:include href="headerback.xsl" />

  <!-- Local includes -->
  <xsl:include href="local/footer.xsl" />
  <xsl:include href="local/headerback.xsl" />
  <xsl:include href="local/headeradminproject.xsl" />

  <xsl:output method="xml" indent="yes"
    doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
    doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
  <xsl:template match="/">
    <html>
      <head>
        <title>
          <xsl:value-of select="cdash/title" />
        </title>
        <meta name="robots" content="noindex,nofollow" />
        <link rel="StyleSheet" type="text/css">
          <xsl:attribute name="href"><xsl:value-of
            select="cdash/cssfile" /></xsl:attribute>
        </link>
        <xsl:if test="/cdash/uselocaldirectory=1">
           <xsl:call-template name="headscripts_local"/>
        </xsl:if>

        <xsl:comment><![CDATA[[if IE]>
          <link rel="stylesheet" href="tabs_ie.css" type="text/css" media="projection, screen" />
          <![endif]]]></xsl:comment>
        <script type="text/javascript" src="js/jquery-1.6.2.js"></script>
        <script type="text/javascript" src="js/ui.tabs.js"></script>
        <script type="text/javascript" src="js/cdashCreateProject.js"></script>
      </head>
      <body bgcolor="#ffffff">

     <xsl:choose>
       <xsl:when test="/cdash/edit=1">
        <xsl:choose>
          <xsl:when test="/cdash/uselocaldirectory=1">
            <xsl:call-template name="headeradminproject_local" />
          </xsl:when>
          <xsl:otherwise>
            <xsl:call-template name="headeradminproject" />
          </xsl:otherwise>
       </xsl:choose>
       </xsl:when>
       <xsl:otherwise>
        <xsl:choose>
          <xsl:when test="/cdash/uselocaldirectory=1">
           <xsl:call-template name="headerback_local" />
          </xsl:when>
          <xsl:otherwise>
            <xsl:call-template name="headerback" />
          </xsl:otherwise>
       </xsl:choose>
       </xsl:otherwise>
     </xsl:choose>

        <br />
        <xsl:value-of select="cdash/alert" />

        <xsl:choose>
          <xsl:when test="cdash/project_created=1">
            The project
            <b>
              <xsl:value-of select="cdash/project_name" />
            </b>
            has been created successfully.
            <br />
            <br />
            Click here to access the
            <a>
              <xsl:attribute name="href">index.php?project=<xsl:value-of
                select="cdash/project_name_encoded" /></xsl:attribute>
              CDash project page
            </a>
            <br />
            Click here to
            <a>
              <xsl:attribute name="href">createProject.php?projectid=<xsl:value-of
                select="cdash/project_id" /></xsl:attribute>
              edit the project
            </a>
            <br />
            Click here to
            <a>
              <xsl:attribute name="href">generateCTestConfig.php?projectid= <xsl:value-of
                select="cdash/project_id" />
</xsl:attribute>
              download the CTest configuration file
            </a>
            <br />
          </xsl:when>
          <xsl:otherwise>

            <xsl:if test="cdash/edit=1">
              <table>
                <tr>
                  <td width="99"></td>
                  <td>
                    <div align="right">
                      <strong>Project:</strong>
                    </div>
                  </td>
                  <td>
                    <select
                      onchange="location = 'createProject.php?projectid='+this.options[this.selectedIndex].value;"
                      name="projectSelection">
                      <option>
                        <xsl:attribute name="value">0</xsl:attribute>
                        Choose...
                      </option>

                      <xsl:for-each select="cdash/availableproject">
                        <option>
                          <xsl:attribute name="value"><xsl:value-of
                            select="id" /></xsl:attribute>
                          <xsl:if test="selected=1">
                            <xsl:attribute name="selected"></xsl:attribute>
                          </xsl:if>
                          <xsl:value-of select="name" />
                        </option>
                      </xsl:for-each>
                    </select>
                  </td>
                </tr>
              </table>
            </xsl:if>

            <xsl:if test="count(cdash/project)>0 or cdash/edit=0">
              <form name="form1" enctype="multipart/form-data" method="post"
                action="">
                <div id="wizard">
                  <ul>
                    <li>
                      <a href="#fragment-1">
                        <span>Information</span>
                      </a>
                    </li>
                    <li>
                      <xsl:if test="cdash/edit=0">
                        <xsl:attribute name="class">
                     tabs-disabled
                    </xsl:attribute>
                      </xsl:if>
                      <a href="#fragment-2">
                        <span>Logo</span>
                      </a>
                    </li>
                    <li>
                      <xsl:if test="cdash/edit=0">
                        <xsl:attribute name="class">
                     tabs-disabled
                    </xsl:attribute>
                      </xsl:if>
                      <a href="#fragment-3">
                        <span>Repository</span>
                      </a>
                    </li>

                    <li>
                      <xsl:if test="cdash/edit=0">
                        <xsl:attribute name="class">
                     tabs-disabled
                    </xsl:attribute>
                      </xsl:if>
                      <a href="#fragment-4">
                        <span>Testing</span>
                      </a>
                    </li>
                    <li>
                      <xsl:if test="cdash/edit=0">
                        <xsl:attribute name="class">
                     tabs-disabled
                    </xsl:attribute>
                      </xsl:if>
                      <a href="#fragment-5">
                        <span>E-mail</span>
                      </a>
                    </li>

                    <xsl:if test="cdash/edit=1">
                      <li><a href="#fragment-6"><span>Spam</span></a></li>
                      <li><a href="#fragment-7"><span>Clients</span></a></li>
                    </xsl:if>


                    <li>
                      <xsl:if test="cdash/edit=0">
                        <xsl:attribute name="class">
                     tabs-disabled
                    </xsl:attribute>
                      </xsl:if>
                      <a><xsl:attribute name="href">
                        <xsl:if test="cdash/edit=0">#fragment-6</xsl:if>
                        <xsl:if test="cdash/edit=1">#fragment-8</xsl:if>
                        </xsl:attribute>
                        <span>Miscellaneous</span>
                      </a>
                    </li>

                  </ul>

                  <div id="fragment-1" class="tab_content">
                    <div class="tab_help"></div>
                    <table width="550">
                      <xsl:if test="cdash/edit=0">
                        <tr>
                          <td></td>
                          <td>
                            <div align="right">
                              <strong>Name:</strong>
                            </div>
                          </td>
                          <td>
                            <input name="name" onchange="saveChanges();"
                              onfocus="showHelp('name_help');" type="text" id="name" />
                            <span class="help_content" id="name_help">
                              <strong>Name of the project.</strong>
                              <br />
                              CDash allows spaces for the name of the project but it is
                              not recommended. If the project’s name contains space
                              make sure you replace the space by the corresponding HTML
                              entity, i.e. %20.
                            </span>
                          </td>
                        </tr>
                      </xsl:if>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Description:</strong>
                          </div>
                        </td>
                        <td>
                          <textarea name="description" onchange="saveChanges();"
                            onfocus="$('.tab_help').html('');" id="description" cols="40"
                            rows="5">
                            <xsl:value-of select="cdash/project/description" />
                          </textarea>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Home URL :</strong>
                          </div>
                        </td>
                        <td>
                          <input name="homeURL" onchange="saveChanges();"
                            onfocus="showHelp('homeurl_help');" type="text" id="homeURL"
                            size="50">
                            <xsl:attribute name="value">
                <xsl:value-of select="cdash/project/homeurl" />
                </xsl:attribute>
                          </input>
                          <span class="help_content" id="homeurl_help">
                            <strong>Home URL</strong>
                            <br />
                            Home url of the project (with or without http://) . This
                            URL is referred in the top menu of the dashboard for this
                            project.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Bug Tracker URL:</strong>
                          </div>
                        </td>
                        <td>
                          <input name="bugURL" onchange="saveChanges();"
                            onfocus="$('.tab_help').html('');" type="text" id="bugURL"
                            size="50">
                            <xsl:attribute name="value">
                    <xsl:value-of select="cdash/project/bugurl" />
                    </xsl:attribute>
                          </input>
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Bug Tracker File URL:</strong>
                          </div>
                        </td>
                        <td>
                          <input name="bugFileURL" onchange="saveChanges();"
                            onfocus="$('.tab_help').html('');" type="text" id="bugFileURL"
                            size="50">
                            <xsl:attribute name="value">
                    <xsl:value-of select="cdash/project/bugfileurl" />
                    </xsl:attribute>
                          </input>
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Documentation URL:</strong>
                          </div>
                        </td>
                        <td>
                          <input name="docURL" onchange="saveChanges();" type="text"
                            onfocus="$('.tab_help').html('');" id="docURL" size="50">
                            <xsl:attribute name="value">
                    <xsl:value-of select="cdash/project/docurl" />
                    </xsl:attribute>
                          </input>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Public Dashboard:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('public_help');"
                            type="checkbox" name="public" value="1">
                            <xsl:if test="cdash/project/public=1">
                              <xsl:attribute name="checked"></xsl:attribute>
                            </xsl:if>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('public_help');" src="img/help.gif"
                              border="0" />
                          </a>
                          <span class="help_content" id="public_help">
                            <b>Public dashboard</b>
                            <br />
                            if the box is checked that means that the dashboard is
                            public and anybody can access the dashboard, claim sites
                            and look at the current status. By default dashboards are
                            private.
                          </span>
                        </td>
                      </tr>

                      <tr>
                        <td>
                        </td>
                        <td>
                        </td>
                        <td align="right">
                          <xsl:if test="cdash/edit=0">
                            <img src="img/next.png" style="cursor:pointer;"
                              onclick="nextTab(1);" alt="next" class="tooltip" title="Next Step" />
                          </xsl:if>
                        </td>
                      </tr>
                    </table>
                  </div>
                  <div id="fragment-2" class="tab_content">
                    <div class="tab_help"></div>
                    <table width="550">
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Logo:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" type="file" name="logo"
                            size="40" />
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                          <span class="help_content" id="logo_help">
                            <strong>Logo</strong>
                            <br />
                            Small logo for this project. It is recommended to upload a
                            transparent gif to blend with CDash’s banner. The height
                            of the image shouldn’t be more than 100 pixels an
                            optimized look. Project logos are stored in the database
                            directly.
                          </span>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            onmouseover="showHelp('logo_help');" target="blank">
                            <img src="img/help.gif" border="0" />
                          </a>
                        </td>
                      </tr>
                      <xsl:if test="cdash/edit=1">
                        <tr>
                          <td></td>
                          <td>
                            <div valign="top" align="right">
                              <strong>Current logo:</strong>
                            </div>
                          </td>
                          <td>
                            <xsl:if test="cdash/project/imageid=0">
                              [none]
                            </xsl:if>
                            <img id="projectlogo" border="0">
                              <xsl:attribute name="alt"><xsl:value-of
                                select="cdash/dashboard/project/name" /></xsl:attribute>
                              <xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of
                                select="cdash/project/imageid" /></xsl:attribute>
                            </img>
                          </td>
                        </tr>
                      </xsl:if>
                      <tr>
                        <td>
                        </td>
                        <td>
                        </td>
                        <td align="right">
                          <br />
                          <br />
                          <br />
                          <br />
                          <br />
                          <br />
                          <xsl:if test="cdash/edit=0">
                            <img src="img/previous.png" style="cursor:pointer;"
                              onclick="previousTab(2);" alt="previous" class="tooltip"
                              title="Previous Step" />
                            <img src="img/next.png" style="cursor:pointer;"
                              onclick="nextTab(2);" alt="next" class="tooltip" title="Next Step" />
                          </xsl:if>
                        </td>
                      </tr>
                    </table>
                  </div>
                  <div id="fragment-3" class="tab_content">
                    <div class="tab_help"></div>
                    <table width="550">
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Repository Viewer URL:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges(); changeViewerType();" onfocus="showHelp('svnViewer_help');"
                            name="cvsURL" type="text" id="cvsURL" size="50">
                            <xsl:attribute name="value">
                  <xsl:value-of select="cdash/project/cvsurl" />
                  </xsl:attribute>
                          </input>
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('svnViewer_help');" src="img/help.gif"
                              border="0" />
                          </a>
                          <span class="help_content" id="svnViewer_help">
                            <b>Repository Viewer URL</b>
                            URL of the Repository viewer
                            <ul>
                              <li> ViewCVS:
                                public.kitware.com/cgi-bin/viewcvs.cgi/?cvsroot=CMake
                              </li>
                              <li>
                                WebSVN:
                                <a
                                  href="https://www.kitware.com/websvn/listing.php?repname=MyRepository"
                                  class="external free"
                                  title="https://www.kitware.com/websvn/listing.php?repname=MyRepository"
                                  rel="nofollow">https://www.kitware.com/websvn/listing.php?repname=MyRepository</a>
                                <br />
                                <b>(listing.php is important)</b>
                              </li>
                            </ul>
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Repository Viewer Type:</strong>
                          </div>
                        </td>
                        <td>
                          <select onchange="saveChanges();changeViewerType();" onfocus="showHelp('svnViewerType_help');"
                            id="cvsviewertype" name="cvsviewertype">
                            <xsl:for-each select="/cdash/cvsviewer">
                              <option>
                                <xsl:attribute name="value"><xsl:value-of
                                  select="value" /></xsl:attribute>
                                <xsl:if test="selected=1">
                                  <xsl:attribute name="selected">selected</xsl:attribute>
                                </xsl:if>
                                <xsl:value-of select="description" />
                              </option>
                            </xsl:for-each>
                          </select>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('svnViewerType_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="svnViewerType_help">
                            <b>Repository View Type</b>
                            <br />
                            Select an appropriate repository viewer depending on
                            your current configuration.
                          </span>
                          <span class="help_content" id="svnRepository_help">
                            <b>Repository</b>
                            <br />
                            In order to get the daily updates, CDash should be able to
                            access the current repository. It is recommended to use
                            the anonymous access, for instance
                            :pserver:anoncvs@myproject.org:/cvsroot/MyProject. If the
                            project needs ssh access, make sure that the user running
                            the webserver running CDash has the proper ssh keys.
                          </span>
                          <span class="help_content" id="svnUsername_help">
                            <b>Username</b>
                            <br />
                            Optional. Provide a username if you do not wish to use anonymous access to your repository.
                          </span>
                          <span class="help_content" id="svnPassword_help">
                            <b>Password</b>
                            <br />
                            The password corresponding to the above user.  WARNING: this password will be stored in plaintext in the database.
                          </span>
                        </td>
                      </tr>
                       <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Test URL:</strong>
                          </div>
                          </td>
                        <td><font size="1"><span id="repositoryurlexample"/></font></td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Repository Robot:</strong>
                          </div>
                        </td>
                        <td>
                         <input onchange="saveChanges();" onfocus="showHelp('cvsrobot_help');"
                            name="robotname" type="text" id="robotname" size="15">
                            <xsl:attribute name="value">
                               <xsl:value-of select="cdash/project/robotname" />
                             </xsl:attribute>
                          </input> regex:
                          <input onchange="saveChanges();" onfocus="showHelp('cvsrobot_help');"
                            name="robotregex" type="text" id="robotregex" size="22">
                            <xsl:attribute name="value">
                               <xsl:value-of select="cdash/project/robotregex" />
                             </xsl:attribute>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('cvsrobot_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="cvsrobot_help">
                            <b>Repository Robot</b>
                            <br />
                            Some repositories have a robot in charge of checking in files
                            from another repository. For CDash to be able to assign an author to the checkin
                            files a regular expression must be defined to allow extraction of the author name
                            from the robot checkin.
                          </span>
                        </td>
                      </tr>
                      <xsl:for-each select="/cdash/cvsrepository">
                        <tr>
                          <td></td>
                          <td>
                            <div align="right">
                              <strong>Repository:</strong>
                            </div>
                          </td>
                          <td>
                            <input onchange="saveChanges();" onfocus="showHelp('svnRepository_help');"
                              type="text" size="50">
                              <xsl:attribute name="name">cvsRepository[<xsl:value-of select="id" />]</xsl:attribute>
                              <xsl:attribute name="value"><xsl:value-of select="url" /></xsl:attribute>
                            </input>
                            <xsl:text disable-output-escaping="yes"> </xsl:text>
                            <a
                              href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                              target="blank">
                              <img onmouseover="showHelp('svnRepository_help');"
                                src="img/help.gif" border="0" />
                            </a>
                          </td>
                        </tr>
                        <tr>
                          <td></td>
                          <td>
                            <div align="right"><strong>Branch:</strong></div>
                          </td>
                          <td>
                            <input onchange="saveChanges();" type="text" size="50">
                              <xsl:attribute name="name">cvsBranch[<xsl:value-of select="id"/>]</xsl:attribute>
                              <xsl:attribute name="value">
                                <xsl:value-of select="branch"/>
                              </xsl:attribute>
                            </input>
                          </td>
                        </tr>
                        <tr>
                          <td></td>
                          <td>
                            <div align="right"><strong>Username:</strong></div>
                          </td>
                          <td>
                            <input onchange="saveChanges();" onfocus="showHelp('svnUsername_help');" type="text" size="50">
                              <xsl:attribute name="name">cvsUsername[<xsl:value-of select="id"/>]</xsl:attribute>
                              <xsl:attribute name="value">
                                <xsl:value-of select="username"/>
                              </xsl:attribute>
                            </input>
                            <xsl:text disable-output-escaping="yes"> </xsl:text>
                            <a href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project" target="blank">
                            <img onmouseover="showHelp('svnUsername_help');" src="img/help.gif" border="0"/></a>
                          </td>
                        </tr>
                        <tr>
                          <td></td>
                          <td>
                            <div align="right"><strong>Password:</strong></div>
                          </td>
                          <td>
                            <input onchange="saveChanges();" onfocus="showHelp('svnPassword_help');" type="password" size="50">
                              <xsl:attribute name="name">cvsPassword[<xsl:value-of select="id"/>]</xsl:attribute>
                              <xsl:attribute name="value">
                                <xsl:value-of select="password"/>
                              </xsl:attribute>
                            </input>
                            <xsl:text disable-output-escaping="yes"> </xsl:text>
                            <a href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project" target="blank">
                            <img onmouseover="showHelp('svnPassword_help');" src="img/help.gif" border="0"/></a>
                          </td>
                        </tr>
                      </xsl:for-each>
                      <xsl:if test="cdash/edit=1">
                        <tr>
                          <td></td>
                          <td></td>
                          <td>
                            <input name="AddRepository" type="submit"
                              value="Add another repository" />
                            <input name="nRepositories" type="hidden">
                              <xsl:attribute name="value">
                    <xsl:value-of select="cdash/nrepositories" />
                  </xsl:attribute>
                            </input>
                          </td>
                        </tr>
                      </xsl:if>
                      <tr>
                        <td>
                        </td>
                        <td>
                        </td>
                        <td align="right">
                          <br />
                          <br />
                          <br />
                          <br />
                          <br />
                          <xsl:if test="cdash/edit=0">
                            <img src="img/previous.png" style="cursor:pointer;"
                              onclick="previousTab(3);" alt="previous" class="tooltip"
                              title="Previous Step" />
                            <img src="img/next.png" style="cursor:pointer;"
                              onclick="nextTab(3);" alt="next" class="tooltip" title="Next Step" />
                          </xsl:if>
                        </td>
                      </tr>
                    </table>
                  </div>
                  <div id="fragment-4" class="tab_content">
                    <div class="tab_help"></div>
                    <table width="550">
                    <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Testing Data URL:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('TestingDataUrl_help');"
                            name="testingDataUrl" type="text" id="testingDataUrl" size="30">
                            <xsl:attribute name="value">
                  <xsl:if test="string-length(cdash/project/testingdataurl)=0"></xsl:if>
                    <xsl:value-of select="cdash/project/testingdataurl" />
                  </xsl:attribute>
                          </input>
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('TestingDataUrl_help');" src="img/help.gif"
                              border="0" />
                          </a>
                          <span class="help_content" id="TestingDataUrl_help">
                            <b>Testing Data URL:</b>
                            <br />
                            CDash can display a link on the main dashboard page
                            to the URL of your testing data
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Nightly Start Time:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('NightlyStart_help');"
                            name="nightlyTime" type="text" id="nightlyTime" size="20">
                            <xsl:attribute name="value">
                  <xsl:if test="string-length(cdash/project/nightlytime)=0">01:00:00 UTC</xsl:if>
                    <xsl:value-of select="cdash/project/nightlytime" />
                  </xsl:attribute>
                          </input>
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('NightlyStart_help');" src="img/help.gif"
                              border="0" />
                          </a>
                          <span class="help_content" id="NightlyStart_help">
                            <b>Nightly start time:</b>
                            <br />
                            CDash displays the current dashboard using a 24hours
                            window. The nightly start time defines the beginning of
                            this window. Note that the start time is expressed in the
                            form HH:MM:SS TZ, i.e. 21:00:00 EDT.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Coverage Threshold:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('CoverageThres_help');"
                            name="coverageThreshold" type="text" id="coverageThreshold"
                            size="2" value="70">
                            <xsl:attribute name="value">
                  <xsl:if
                              test="string-length(cdash/project/coveragethreshold)=0">70</xsl:if>
                  <xsl:value-of select="cdash/project/coveragethreshold" />
                  </xsl:attribute>
                          </input>
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('CoverageThres_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="CoverageThres_help">
                            <b>Coverage threshold</b>
                            <br />
                            CDash marks the coverage has passed (green) if the global
                            coverage for a build or specific files is above this
                            threshold. It is recommended to set the coverage threshold
                            to a high value and increase it when the coverage is
                            getting higher.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Enable test timing:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('EnableTestTiming_help');"
                            name="showTestTime" type="checkbox" value="1">
                            <xsl:if test="cdash/project/showtesttime=1">
                              <xsl:attribute name="checked"></xsl:attribute>
                            </xsl:if>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('EnableTestTiming_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="EnableTestTiming_help">
                            <b>Enable test timing</b>
                            <br />
                            Enable/Disable test timing for this project
                            <p>
                              For more information about test timing see the
                              <a href="http://www.cdash.org/Wiki/CDash:Administration#Test_Timing" title="CDash:Administration" target="blank">
                                Test Timing</a>
                              section.
                            </p>
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Test time standard deviation multiplier:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('TimeDeviation_help');"
                            name="testTimeStd" type="text" id="testTimeStd" size="4">
                            <xsl:attribute name="value">
                    <xsl:if
                              test="string-length(cdash/project/testtimestd)=0">4.0</xsl:if>
                    <xsl:value-of select="cdash/project/testtimestd" />
                  </xsl:attribute>
                          </input>
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('TimeDeviation_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="TimeDeviation_help">
                            <b>Test time standard deviation multiplier</b>
                            <br />
                            Set a multiplier for the standard deviation for a test
                            time. If the time for a test is higher than
                            mean+multiplier*standarddeviation, the test time status is
                            marked as failed. Default is 4 if not specified. Note that
                            changing this value doesn’t affect previous builds but
                            only builds submitted after the modification.
                            <p>
                              For more information about test timing see the
                              <a href="http://www.cdash.org/Wiki/CDash:Administration#Test_Timing" title="CDash:Administration" target="blank">
                                Test Timing</a>
                              section.
                            </p>
                          </span>
                        </td>
                      </tr>

                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Test time standard deviation threshold:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();"
                            onfocus="showHelp('TimeDeviationThreshold_help');" name="testTimeStdThreshold"
                            type="text" id="testTimeStdThreshold" size="4">
                            <xsl:attribute name="value">
                    <xsl:if
                              test="string-length(cdash/project/testtimestdthreshold)=0">1.0</xsl:if>
                    <xsl:value-of select="cdash/project/testtimestdthreshold" />
                  </xsl:attribute>
                          </input>
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('TimeDeviationThreshold_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="TimeDeviationThreshold_help">
                            <b>Test time standard deviation threshold</b>
                            <br />
                            Set a minimum standard deviation for a test time. If the
                            current standard deviation for a test is lower than this
                            threshold then the threshold is used instead. This is
                            particularly important, for tests that have a very low
                            standard deviation but still some variability. Default
                            threshold is set to 2 if not specified. Note that changing
                            this value doesn’t affect previous builds but only builds
                            submitted after the modification.
                            <p>
                              For more information about test timing see the
                              <a href="/Wiki/CDash:Administration#Test_Timing" title="CDash:Administration" target="blank">
                                Test Timing</a>
                              section.
                            </p>
                          </span>
                        </td>
                      </tr>

                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Test time # max failures before flag:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="$('.tab_help').html('');"
                            name="testTimeMaxStatus" type="text" id="testTimeMaxStatus"
                            size="4">
                            <xsl:attribute name="value">
                    <xsl:if
                              test="string-length(cdash/project/testtimemaxstatus)=0">3</xsl:if>
                    <xsl:value-of select="cdash/project/testtimemaxstatus" />
                  </xsl:attribute>
                          </input>
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                        </td>
                      </tr>
                      <tr>
                        <td>
                        </td>
                        <td>
                        </td>
                        <td align="right">
                          <xsl:if test="cdash/edit=0">
                            <img src="img/previous.png" style="cursor:pointer;"
                              onclick="previousTab(4);" alt="previous" class="tooltip"
                              title="Previous Step" />
                            <img src="img/next.png" style="cursor:pointer;"
                              onclick="nextTab(4);" alt="next" class="tooltip" title="Next Step" />
                          </xsl:if>
                        </td>
                      </tr>
                    </table>
                  </div>

                  <div id="fragment-5" class="tab_content">
                    <div class="tab_help"></div>
                    <table width="550">
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Email submission failures:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('emailBroken_help');"
                            type="checkbox" name="emailBrokenSubmission" value="1">
                            <xsl:if test="cdash/project/emailbrokensubmission=1">
                              <xsl:attribute name="checked"></xsl:attribute>
                            </xsl:if>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('emailBroken_help');" src="img/help.gif"
                              border="0" />
                          </a>
                          <span class="help_content" id="emailBroken_help">
                            <b>Email broken submission</b>
                            <br />
                            Enable/Disable sending email for broken submissions for
                            this project. This is a general feature.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Email redundant failures:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('emailRedundant_help');"
                            type="checkbox" name="emailRedundantFailures" value="1">
                            <xsl:if test="cdash/project/emailredundantfailures=1">
                              <xsl:attribute name="checked"></xsl:attribute>
                            </xsl:if>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('emailRedundant_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="emailRedundant_help">
                            <b>Email redundant failures</b>
                            <br />
                            Enable/Disable sending email even if a build has been
                            failing previously. If not checked, CDash sends an email
                            only on the first failure.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Email administrator:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();"
                            onfocus="showHelp('emailAdministrator_help');" type="checkbox"
                            name="emailAdministrator" value="1">
                            <xsl:if test="cdash/project/emailadministrator=1">
                              <xsl:attribute name="checked"></xsl:attribute>
                            </xsl:if>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('emailAdministrator_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="emailAdministrator_help">
                            <b>Email administator</b>
                            <br />
                            Enable/Disable sending email when the XML parsing fails or
                            any issues related to the project administration.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Email low coverage:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('emailCoverage_help');"
                            type="checkbox" name="emailLowCoverage" value="1">
                            <xsl:if test="cdash/project/emaillowcoverage=1">
                              <xsl:attribute name="checked"></xsl:attribute>
                            </xsl:if>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('emailCoverage_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="emailCoverage_help">
                            <b>Email low coverage</b>
                            <br />
                            Enable/Disable sending email when the coverage for files
                            is lower than the "Coverage Threshold" value specified on
                            the "Testing" tab.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Email test timing changed:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('emailTiming_help');"
                            type="checkbox" name="emailTestTimingChanged" value="1">
                            <xsl:if test="cdash/project/emailtesttimingchanged=1">
                              <xsl:attribute name="checked"></xsl:attribute>
                            </xsl:if>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('emailTiming_help');" src="img/help.gif"
                              border="0" />
                          </a>
                          <span class="help_content" id="emailTiming_help">
                            <b>Email test timing change</b>
                            <br />
                            Enable/Disable sending email when a test timing has
                            changed. This feature is currently not implemented.
                          </span>
                        </td>
                      </tr>

                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Maximum number of items in email:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="$('.tab_help').html('');"
                            name="emailMaxItems" type="text" id="emailMaxItems" size="4">
                            <xsl:attribute name="value">
                  <xsl:if
                              test="string-length(cdash/project/emailmaxitems)=0">5</xsl:if>
                  <xsl:value-of select="cdash/project/emailmaxitems" />
                </xsl:attribute>
                          </input>
                        </td>
                      </tr>

                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Maximum number of characters per item in email:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="$('.tab_help').html('');"
                            name="emailMaxChars" type="text" id="emailMaxChars" size="4">
                            <xsl:attribute name="value">
                  <xsl:if
                              test="string-length(cdash/project/emailmaxchars)=0">255</xsl:if>
                  <xsl:value-of select="cdash/project/emailmaxchars" />
                </xsl:attribute>
                          </input>
                        </td>
                      </tr>
                      <tr>
                        <td>
                        </td>
                        <td>
                        </td>
                        <td align="right">
                          <xsl:if test="cdash/edit=0">
                            <img src="img/previous.png" style="cursor:pointer;"
                              onclick="previousTab(5);" alt="previous" class="tooltip"
                              title="Previous Step" />
                            <img src="img/next.png" style="cursor:pointer;"
                              onclick="nextTab(5);" alt="next" class="tooltip" title="Next Step" />
                          </xsl:if>
                        </td>
                      </tr>
                    </table>
                  </div>

                <xsl:if test="cdash/edit=1">
                  <div id="fragment-6" class="tab_content">
                    <div class="tab_help"></div>
                    <table width="550">
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>
                              Block List
                              <a
                                href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                                target="blank">
                                <img onmouseover="showHelp('blockList_help');"
                                  src="img/help.gif" border="0" />
                              </a>
                            </strong>
                          </div>
                        </td>
                        <td>
                          <table width="100%" border="0">
                            <xsl:for-each select="/cdash/blockedbuild">
                              <tr>
                                <td>
                                  <xsl:value-of select="name" />
                                </td>
                                <td>
                                  <xsl:value-of select="site" />
                                </td>
                                <td>
                                  <xsl:value-of select="ip" />
                                </td>
                                <td>
                                  <input type="checkbox" value="1">
                                    <xsl:attribute name="name">removespam[<xsl:value-of
                                      select="id" />]</xsl:attribute>
                                  </input>
                                </td>
                              </tr>
                            </xsl:for-each>

                          </table>
                        </td>
                        <span class="help_content" id="blockList_help">
                          <b>Block List</b>
                          <br />
                          Submission to CDash can be blocked given a sitename,
                          buildname and IP address in order to prevent submissions
                          from unwanted host.
                        </span>
                      </tr>
                      <xsl:if test="count(/cdash/blockedbuild) >0">
                        <tr>
                          <td></td>
                          <td></td>
                          <td>
                            <input type="submit" name="RemoveSpamFilter" value="Remove Selected" />
                          </td>
                        </tr>
                      </xsl:if>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Build Name:</strong>
                          </div>
                        </td>
                        <td>
                          <input type="text" name="spambuildname" />
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Site Name:</strong>
                          </div>
                        </td>
                        <td>
                          <input type="text" name="spamsitename" />
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>IP Address:</strong>
                          </div>
                        </td>
                        <td>
                          <input type="text" name="spamip" />
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td></td>
                        <td>
                          <input type="submit" name="SpamFilter" value="Add filter" />
                        </td>
                      </tr>
                    <tr>
                        <td>
                        </td>
                        <td>
                        </td>
                        <td align="right">
                        <xsl:if test="cdash/edit=0">
                            <img src="img/previous.png" style="cursor:pointer;"
                              onclick="previousTab(6);" alt="previous" class="tooltip"
                              title="Previous Step" />
                            <img src="img/next.png" style="cursor:pointer;"
                              onclick="nextTab(6);" alt="next" class="tooltip" title="Next Step" />
                        </xsl:if>
                        </td>
                      </tr>
                    </table>
                  </div>
                  </xsl:if> <!--  end if edit mode -->


         <div class="tab_content" id="fragment-7">
                    <div class="tab_help"></div>
                    <table width="100%">
                        <tr>
                          <td valign="top" align="right"><strong>CTest Template Script:</strong>
                          </td>
                          <td >
                           <textarea name="ctestTemplateScript" onchange="saveChanges();"
                            onfocus="$('.ctesttemplatescript_help').html('');" id="ctestScript" cols="80"
                            rows="30" wrap="off">
                            <xsl:value-of select="cdash/project/ctesttemplatescript" />
                          </textarea>
                          </td>
                        </tr>

            <tr>
                        <td>
                        </td>
                        <td align="right">
                        <xsl:if test="cdash/edit=0">
                            <img src="img/previous.png" style="cursor:pointer;"
                              onclick="previousTab(7);" alt="previous" class="tooltip"
                              title="Previous Step" />
                            <img src="img/next.png" style="cursor:pointer;"
                              onclick="nextTab(7);" alt="next" class="tooltip" title="Next Step" />
                        </xsl:if>
                        </td>
                      </tr>
                    </table>
                  </div>



                  <div class="tab_content">
                   <xsl:attribute name="id">
                     <xsl:if test="cdash/edit=0">fragment-6</xsl:if>
                     <xsl:if test="cdash/edit=1">fragment-8</xsl:if>
                     </xsl:attribute>

                    <div class="tab_help"></div>
                    <table width="550">

                      <!-- downloading the CTestConfig.cmake -->
                      <xsl:if test="cdash/edit=1">
                        <tr>
                          <td></td>
                          <td>
                            <div align="right">
                              <strong>Download CTestConfig:</strong>
                            </div>
                          </td>
                          <td>
                            <a>
                              <xsl:attribute name="href">generateCTestConfig.php?projectid= <xsl:value-of
                                select="cdash/project/id" />
                </xsl:attribute>
                              CTestConfig.cmake
                            </a>
                            <a
                              href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                              target="blank">
                              <img onmouseover="showHelp('ctestConfig_help');" src="img/help.gif"
                                border="0" />
                            </a>
                          </td>
                          <span class="help_content" id="ctestConfig_help">
                            <b>Download CTest config</b>
                            <br />
                            Automatically generated CTest configuration file.
                            downloading this file and putting it at the root of your
                            project, allows to quickly get started with CTest/CDash
                            and submitting to the dashboard.
                          </span>
                        </tr>
                      </xsl:if>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Google Analytics Tracker:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('google_help');"
                            name="googleTracker" type="text" id="googleTracker" size="30">
                            <xsl:attribute name="value">
                      <xsl:value-of select="cdash/project/googletracker" />
                    </xsl:attribute>
                          </input>
                          <xsl:text disable-output-escaping="yes"> </xsl:text>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('google_help');" src="img/help.gif"
                              border="0" />
                          </a>
                          <span class="help_content" id="google_help">
                            <b>Google Analytics Tracker</b>
                            <br />
                            CDash supports visitor tracking through Google analytics.
                            See “Adding Google Analytics” for more information.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Show site IP addresses:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();"
                            onfocus="showHelp('showSiteIPAddresses_help');" type="checkbox"
                            name="showIPAddresses" value="1">
                            <xsl:if test="cdash/project/showipaddresses=1">
                              <xsl:attribute name="checked"></xsl:attribute>
                            </xsl:if>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('showSiteIPAddresses_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="showSiteIPAddresses_help">
                            <b>Show Site IP Addresses</b>
                            <br />
                            Enable/Disable the display of IP addresses of the sites
                            submitting to this project.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Display Labels:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('displayLabels_help');"
                            type="checkbox" name="displayLabels" value="1">
                            <xsl:if test="cdash/project/displaylabels=1">
                              <xsl:attribute name="checked"></xsl:attribute>
                            </xsl:if>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('displayLabels_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="displayLabels_help">
                            <b>Display Labels</b>
                            <br />
                            Enable/Disable the display of the label column for the
                            project. The labels are submitted by the client as part of
                            the submission.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Show coverage code:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('showCoverageCode_help');"
                            type="checkbox" name="showCoverageCode" value="1">
                            <xsl:if test="cdash/project/showcoveragecode=1">
                              <xsl:attribute name="checked"></xsl:attribute>
                            </xsl:if>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('showCoverageCode_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="showCoverageCode_help">
                            <b>Display Source Code in Coverage</b>
                            <br />
                            Enable/Disable the display of code coverage for the project. Only administrators
                            of the projects can see the source code in the coverage section when this option is disabled.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>AutoRemove Timeframe (days):</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('autoremoveTimeframe_help');"
                            name="autoremoveTimeframe" type="text" id="autoremoveTimeframe"
                            size="10">
                            <xsl:attribute name="value">
                              <xsl:value-of select="cdash/project/autoremovetimeframe" />
                            </xsl:attribute>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('autoremoveTimeframe_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="autoremoveTimeframe_help">
                            <b>AutoRemove Timeframe</b>
                            <br />
                            On the first submission of the day, remove builds that are
                            older than X number of days.
                            If this value is less than 2 days, no builds are removed.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>AutoRemove Max Builds:</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('autoremoveMaxBuilds_help');"
                            name="autoremoveMaxBuilds" type="text" id="autoremoveMaxBuilds"
                            size="10">
                            <xsl:attribute name="value">
                   <xsl:value-of select="cdash/project/autoremovemaxbuilds" />
                 </xsl:attribute>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('autoremoveMaxBuilds_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="autoremoveMaxBuilds_help">
                            <b>AutoRemove max builds</b>
                            <br />
                            On the first submission of the day, remove builds that are
                            older than X number of days.
                            The maximum number of builds that should be removed.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>File upload quota (GB):</strong>
                          </div>
                        </td>
                        <td>
                          <input onchange="saveChanges();" onfocus="showHelp('uploadQuota_help');"
                            name="uploadQuota" type="text" id="uploadQuota"
                            size="10">
                            <xsl:attribute name="value">
                   <xsl:value-of select="cdash/project/uploadquota" />
                 </xsl:attribute>
                          </input>
                          <a
                            href="http://www.cdash.org/Wiki/CDash:Administration#Creating_a_project"
                            target="blank">
                            <img onmouseover="showHelp('uploadQuota_help');"
                              src="img/help.gif" border="0" />
                          </a>
                          <span class="help_content" id="uploadQuota_help">
                            <b>File upload quota</b>
                            <br />
                            Enter how many gigabytes of uploaded files to store with this project.
                            If this quota is exceeded, older files will be deleted to make room when
                            new ones are uploaded. The number must be less than or equal to the maximum
                            per-project quota of <xsl:value-of select="cdash/project/maxuploadquota" /> GB.
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td></td>
                        <td>
                          <div align="right">
                            <strong>Web API Key:</strong>
                          </div>
                        </td>
                        <td onmouseover="showHelp('webapikey_help');" >
                          <xsl:value-of select="cdash/project/webapikey" />
                          <span class="help_content" id="webapikey_help">
                            <b>Web API key</b>
                            <br />
                            Use this key when calling the login method of the web API.
                            It will return a token that you can temporarily use for authenticated access
                            to other web API methods.
                          </span>
                        </td>
                      </tr>


                      <tr>
                        <td>
                        </td>
                        <td>
                        </td>
                        <td align="right">

                          <xsl:if test="cdash/edit=0">
                            <img src="img/previous.png" style="cursor:pointer;"
                              onclick="previousTab(6);" alt="previous" class="tooltip"
                              title="Previous Step" />
                            <input type="submit" name="Submit" value="Create Project >> ">
                              <xsl:if test="cdash/edit=0">
                                <xsl:attribute name="disabled">
                     disabled
                    </xsl:attribute>
                              </xsl:if>
                            </input>
                          </xsl:if>
                          <xsl:if test="cdash/edit=1">
                            <br />
                            <br />
                            <input type="submit" name="Delete" value="Delete Project"
                              onclick="return confirmDelete()" />
                          </xsl:if>
                        </td>
                      </tr>
                    </table>
                  </div>

                </div>


                <xsl:if test="cdash/edit=1">
                  <div
                    style="width:900px;margin-left:auto;margin-right:auto;text-align:right;">
                    <br />
                    <span id="changesmade" style="color:red;display:none;">*Changes need to be updated </span>
                    <input type="submit" name="Update" value="Update Project" />
                  </div>
                </xsl:if>

              </form>
            </xsl:if>
          </xsl:otherwise>
        </xsl:choose>

        <br />
        <!-- FOOTER -->
        <br />
        <xsl:choose>
          <xsl:when test="/cdash/uselocaldirectory=1">
            <xsl:call-template name="footer_local" />
          </xsl:when>
          <xsl:otherwise>
            <xsl:call-template name="footer" />
          </xsl:otherwise>
        </xsl:choose>

      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
