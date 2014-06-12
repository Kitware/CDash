<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="footer.xsl"/>
  <xsl:include href="headscripts.xsl"/>
  <xsl:include href="headeradminproject.xsl"/>

  <!-- Local includes -->
  <xsl:include href="local/footer.xsl"/>
  <xsl:include href="local/headscripts.xsl"/>
  <xsl:include href="local/headeradminproject.xsl"/>

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
  doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

  <xsl:template match="/">
    <html>
      <head>
        <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
        <link rel="StyleSheet" type="text/css">
          <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
        </link>
        <xsl:comment><![CDATA[[if IE]>
          <link rel="stylesheet" href="tabs_ie.css" type="text/css" media="projection, screen" />
          <![endif]]]>
        </xsl:comment>
        <link rel="stylesheet" href="css/jquery-ui.css"/>
        <link rel="stylesheet" href="css/bootstrap.min.css"/>

        <script src="javascript/jquery-1.10.2.js"/>
        <script src="javascript/jquery-ui-1.10.4.min.js"/>
        <script src="javascript/cdashSortable.js"></script>
        <script src="javascript/cdashTableCollapse.js"></script> <!-- for restripe() -->
        <script>
        // copied from old tabs.js.  Needs a new home (not here)...
        function showHelpTop(id_div)
          {
          $(".tab_help_top").html($("#"+id_div).html()).show();
          }

        $(function() {

          // setup sortable table rows
          $( "#sortable" ).sortable(
            {
            stop: function(event, ui)
              {
              restripe("#sortable");
              }
            }
          );
          $( "#sortable" ).disableSelection();

          // setup collapsible table cells
          $( ".toggleCollapse" ).click(function() {
            $(this).closest("tr").find(".collapsible").slideToggle("slow");
            $(this).closest("tr").find(".help").slideToggle("slow");
            if ($(this).hasClass("glyphicon-folder-open")) {
              $(this).removeClass("glyphicon-folder-open");
              $(this).addClass("glyphicon-folder-close");
            }
            else {
              $(this).removeClass("glyphicon-folder-close");
              $(this).addClass("glyphicon-folder-open");
            }
          });

          // save layout function
          $( "#saveLayout" ).click(function() {
            var newLayout = JSON.stringify(getSortedElements("#sortable"));
            $("#loading").attr("src", "images/loading.gif");
            $.ajax(
              {
              url: "manageBuildGroup.php?projectid=<xsl:value-of select="cdash/project/id"/>",
              type: "POST",
              data: {saveLayout : newLayout},
              success: function(data)
                {
                $("#loading").attr("src", "images/check.gif");
                },
              error: function(e, s, t)
                {
                console.log("status: " + s);
                console.log("error thrown: " + t);
                }
              });
            });

          restripe("#sortable");
          $( "#wizard" ).tabs();

        });
        </script>

        <!-- Functions to confirm the remove -->
        <xsl:text disable-output-escaping="yes">
        &lt;script language="javascript" type="text/javascript" &gt;
        function confirmDelete() {
         if (window.confirm("Are you sure you want to delete this group? If the group is not empty, builds will be put in their original group.")){
            return true;
         }
         return false;
        }
        &lt;/script&gt;
        </xsl:text>
      </head>
      <body bgcolor="#ffffff">

        <xsl:choose>
          <xsl:when test="/cdash/uselocaldirectory=1">
            <xsl:call-template name="headeradminproject_local"/>
          </xsl:when>
          <xsl:otherwise>
            <xsl:call-template name="headeradminproject"/>
          </xsl:otherwise>
        </xsl:choose>

        <br/>

        <xsl:choose>
          <xsl:when test="cdash/group_created=1">
            The group <b><xsl:value-of select="cdash/group_name"/></b> has been created successfully.<br/>
            Click here to access the  <a>
              <xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/project_name"/></xsl:attribute>
              project page</a>
          </xsl:when>
          <xsl:otherwise>

            <xsl:if test="string-length(cdash/warning)>0">
              <b>Warning: <xsl:value-of select="cdash/warning"/></b><br/><br/>
            </xsl:if>

            <table border="0">
              <tr>
                <td width="10%"><div align="right"><strong>Project:</strong></div></td>
                <td width="90%" >
                  <form name="form1" method="post">
                    <xsl:attribute name="action">manageBuildGroup.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
                    <select onchange="location = 'manageBuildGroup.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
                      <option>
                        <xsl:attribute name="value">0</xsl:attribute>
                        Choose...
                      </option>

                      <xsl:for-each select="cdash/availableproject">
                        <option>
                          <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                          <xsl:if test="selected=1">
                          <xsl:attribute name="selected"></xsl:attribute>
                          </xsl:if>
                          <xsl:value-of select="name"/>
                        </option>
                      </xsl:for-each>
                    </select>
                  </form>
                </td>
              </tr>
            </table>

            <!-- If a project has been selected -->
            <xsl:if test="count(cdash/project)>0">
              <div id="wizard">
                <ul>
                  <li><a href="#fragment-1">Current groups</a></li>
                  <li><a href="#fragment-2">Create new group</a></li>
                  <li><a href="#fragment-3">Global Move</a></li>
                  <li><a href="#fragment-4">Auto-Remove Settings</a></li>
                  <li><a href="#fragment-5">Define Group by Build Name</a></li>
                </ul>
                <div id="fragment-1" class="tab_content" >
                  <div class="tab_help_top"></div>
                    <table width="870"  border="0">
                      <!-- List the current groups -->
                      <tr>
                        <td><div align="right"></div></td>
                        <td>
                          <table border="0" width="100%">
                            <tbody id="sortable">
                              <xsl:for-each select="cdash/project/group">
                                <tr id="{id}" style="cursor: move;">
                                  <td><xsl:value-of select="name"/></td>
                                  <td>
                                    <form method="post">
                                      <xsl:attribute name="name">form_<xsl:value-of select="id"/></xsl:attribute>
                                      <xsl:attribute name="action">manageBuildGroup.php?projectid=<xsl:value-of select="/cdash/project/id"/></xsl:attribute>
                                      <input type="hidden" name="groupid">
                                        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                                      </input>
                                      <xsl:if test="name!='Nightly' and name!='Experimental' and name !='Continuous'">
                                        <!-- cannot rename Nightly/Continuous/Experimental -->
                                        <input name="newname" type="text" id="newname" size="20"/><input type="submit" name="rename" value="Rename"/>
                                      </xsl:if>
                                      <xsl:if test="name!='Nightly' and name!='Experimental' and name !='Continuous'">
                                        <!-- cannot delete Nightly/Continuous/Experimental -->
                                        <input type="submit" name="deleteGroup" value="Delete" onclick="return confirmDelete()"/>
                                      </xsl:if>
                                    </form>
                                  </td>
                                  <td class="collapsible" style="display:none;">
                                    <form method="post">
                                      <xsl:attribute name="name">form_<xsl:value-of select="id"/>_2</xsl:attribute>
                                      <xsl:attribute name="action">manageBuildGroup.php?projectid=<xsl:value-of select="/cdash/project/id"/></xsl:attribute>
                                      <input type="hidden" name="groupid">
                                        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                                      </input>
                                      <input name="description" type="text" size="30">
                                        <xsl:attribute name="value"><xsl:value-of select="description"/></xsl:attribute>
                                      </input>
                                      <input type="submit" name="submitDescription" value="Update Description"/>
                                      <br/>
                                      <input name="summaryEmail" onclick="form.submit();" type="checkbox" value="1">
                                        <xsl:if test="summaryemail=1">
                                          <xsl:attribute name="checked">checked</xsl:attribute>
                                         </xsl:if>
                                      </input>
                                      Summary email
                                      <br/>
                                      <input name="summaryEmail" onclick="form.submit();" type="checkbox" value="2">
                                        <xsl:if test="summaryemail=2">
                                          <xsl:attribute name="checked">checked</xsl:attribute>
                                        </xsl:if>
                                      </input>
                                      No email
                                      <br/>
                                      <input name="emailCommitters" onclick="form.submit();" type="checkbox" value="1">
                                        <xsl:if test="emailcommitters != 0">
                                          <xsl:attribute name="checked">checked</xsl:attribute>
                                        </xsl:if>
                                      </input>
                                      Email committers
                                      <br/>
                                      <input name="includeInSummary" onclick="form.submit();" type="checkbox" value="1">
                                        <xsl:if test="includeinsummary=1">
                                          <xsl:attribute name="checked">checked</xsl:attribute>
                                         </xsl:if>
                                      </input>
                                      Included in subproject summary
                                      <br/>
                                    </form>
                                  </td>
                                  <td class="help" style="display:none;">
                                    <a href="http://public.kitware.com/Wiki/CDash:Administration#Creating_a_project" target="blank">
                                      <img onmouseover="showHelpTop('summary_help');" src="images/help.gif" border="0"/>
                                    </a>
                                  </td>
                                  <td>
                                    <div class="toggleCollapse glyphicon glyphicon-folder-close" style="padding: 10px;" title="Collapse or expand more options for this build group">
                                    </div>
                                  </td>
                                </tr>
                              </xsl:for-each>
                            </tbody>
                          </table>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <span class="help_content" id="summary_help">
                            <strong>Summary email:</strong> sends only one email per day when the first build in the group fails.<br/>
                            <strong>No email:</strong> doesn't send any email for that group regardless of user's preferences.<br/>
                            <strong>Email committers:</strong> sends email to authors and committers for build problems in the group, even if user is not registered.<br/>
                            <strong>Included in subproject summary:</strong> checked if the group's builds contribute to totals on subproject summary pages.<br/>
                          </span>
                        </td>
                        <td></td>
                      </tr>
                    </table>
                    <br/>
                    <button type="submit" id="saveLayout">Update Group Order</button>
                    <img id="loading" style="height:16px; width=16px; margin-top:9px;"/>
                  </div>
                  <div id="fragment-2" class="tab_content" >
                    <div class="tab_help"></div>
                    <form name="formnewgroup" method="post">
                      <xsl:attribute name="action">manageBuildGroup.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
                      <table width="800"  border="0">
                        <tr>
                          <td width="10%"><div align="right">Name:</div></td>
                          <td width="90%"><input name="name" type="text" id="name" size="40"/></td>
                        </tr>
                        <tr>
                          <td><div align="right"></div></td>
                          <td><input type="submit" name="createGroup" value="Create Group"/><br/><br/></td>
                        </tr>
                      </table>
                    </form>
                  </div>
                  <div id="fragment-3" class="tab_content" >
                    <div class="tab_help"></div>
                    <form name="globalmove" method="post">
                      <xsl:attribute name="action">manageBuildGroup.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
                      <table width="800"  border="0">
                        <tr>
                          <td width="10%"><div align="right">Show:</div></td>
                          <td width="90%" >
                            <select onchange="location = 'manageBuildGroup.php?projectid='+form1.projectSelection.value+'&amp;show='+this.options[this.selectedIndex].value+'&amp;fragment=3';"  name="globalMoveSelectionType">
                              <option>
                                <xsl:attribute name="value">0</xsl:attribute>All
                              </option>
                              <xsl:for-each select="cdash/project/group">
                                <option>
                                  <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                                  <xsl:if test="selected=1">
                                    <xsl:attribute name="selected"></xsl:attribute>
                                  </xsl:if>
                                  <xsl:value-of select="name"/>
                                </option>
                              </xsl:for-each>
                            </select>
                            (showing the builds submitted in the past 7 days and expected builds)
                          </td>
                        </tr>
                          <tr>
                          <td><div align="right"></div></td>
                          <td>
                            <select name="movebuilds[]" size="15" multiple="multiple" id="movebuilds">
                              <xsl:for-each select="cdash/currentbuild">
                                <option>
                                  <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                                  <xsl:value-of select="name"/>
                                </option>
                              </xsl:for-each>
                            </select>
                            <br/>
                            Move to group: (select a group even if you want only expected)
                            <select name="groupSelection">
                              <option>
                                <xsl:attribute name="value">0</xsl:attribute>
                                Choose...
                              </option>
                              <xsl:for-each select="cdash/project/group">
                                <option>
                                  <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                                  <xsl:value-of select="name"/>
                                </option>
                              </xsl:for-each>
                            </select>
                            <br/>
                            <input name="expectedMove" type="checkbox" value="1"/> expected
                            <br/>
                            <input type="submit" name="globalMove" value="Move selected build to group"/>
                          </td>
                        </tr>
                      </table>
                    </form>
                  </div>
                <div id="fragment-4" class="tab_content" >
                  Builds for each group can be automatically removed after a certain number of days.<br/>
                  <form method="post" action="">
                    <table>
                      <xsl:for-each select="cdash/buildgroup">
                        <tr>
                          <td></td>
                          <td>
                            <div align="right">
                              <strong>AutoRemove Time Frame - <xsl:value-of select="name" />:</strong>
                            </div>
                          </td>
                          <td>
                            <input onchange="saveChanges();" type="text" size="10">
                              <xsl:attribute name="value"><xsl:value-of select="autoremovetimeframe" /></xsl:attribute>
                              <xsl:attribute name="name">autoremovetimeframe_<xsl:value-of select="id" /></xsl:attribute>
                              <xsl:attribute name="id">autoremovetimeframe_<xsl:value-of select="id" /></xsl:attribute>
                            </input>
                            <a href="http://public.kitware.com/Wiki/CDash:Administration#Creating_a_project" target="blank">
                              <img src="images/help.gif" border="0" />
                            </a>
                          </td>
                        </tr>
                      </xsl:for-each>
                    </table>
                    <input type="submit" name="submitAutoRemoveSettings" value="Update Settings"/>
                  </form>
                </div>
                <div id="fragment-5" class="tab_content" >
                  <form name="definegroupbybuildname" method="post">
                    <xsl:attribute name="action">manageBuildGroup.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
                    The build names of <select name="groupBuildNameSelection">
                      <option>
                        <xsl:attribute name="value">0</xsl:attribute>
                        Choose...
                      </option>
                      <xsl:for-each select="cdash/project/group">
                        <option>
                          <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                          <xsl:value-of select="name"/>
                        </option>
                      </xsl:for-each>
                    </select>
                    match <input name="buildNameMatch" type="text" id="buildNameMatch" size="20"/>
                    and their type is <select name="buildType">
                      <option>Nightly</option>
                      <option>Continuous</option>
                      <option>Experimental</option>
                    </select>
                    <br/>
                    <input type="submit" name="defineByBuildName" value="Define Group"/>
                    <br/>
                  </form>
                  <br/><br/>
                  <form name="deleterules" method="post">
                    <xsl:attribute name="action">manageBuildGroup.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
                    Delete build group rules for <select name="deleteRulesForGroup">
                      <option>
                        <xsl:attribute name="value">0</xsl:attribute>
                        Choose...
                      </option>
                      <xsl:for-each select="cdash/project/group">
                        <option>
                          <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                          <xsl:value-of select="name"/>
                        </option>
                      </xsl:for-each>
                    </select>
                    <input type="submit" name="deleteBuildGroupRules" value="Delete Rules"/>
                    <br/>
                  </form>
                </div> <!-- fragment -->
              </div> <!-- wizard -->
            </xsl:if>
          <br/>
          </xsl:otherwise>
        </xsl:choose>

      <!-- FOOTER -->
      <br/>
      <br/>
      <xsl:choose>
        <xsl:when test="/cdash/uselocaldirectory=1">
          <xsl:call-template name="footer_local"/>
        </xsl:when>
        <xsl:otherwise>
          <xsl:call-template name="footer"/>
        </xsl:otherwise>
      </xsl:choose>

      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
