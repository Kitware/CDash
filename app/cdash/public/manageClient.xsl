<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="footer.xsl"/>
  <xsl:include href="headerback.xsl"/>

   <!-- Local includes -->
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="local/headerback.xsl"/>

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="UTF-8"/>
  <xsl:template match="/">
      <html>
      <head>
        <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
    <link rel="shortcut icon" href="favicon.ico"/>
        <link rel="StyleSheet" type="text/css">
          <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
        </link>
        <xsl:call-template name="headscripts"/>
        <script src="js/cdashClient.js" type="text/javascript" charset="utf-8"></script>
      </head>

 <body>

<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="headerback_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="headerback"/>
</xsl:otherwise>
</xsl:choose>

<br/>

<!-- Message -->
<div style="color: green;"><xsl:value-of select="cdash/message" /></div>

<xsl:if test="count(cdash/project/repository)=0">
  You should set the <a>
  <xsl:attribute name="href">
    createProject.php?projectid=<xsl:value-of select="cdash/project/id" />##tab3
  </xsl:attribute>
  project repository</a> before starting.<br/>
</xsl:if>
<xsl:if test="count(cdash/os)=0">
No sites are currently available. You should run the CTest script in order to register at least one client.<br/>
Visit <a href="http://public.kitware.com/Wiki/CDash:Build_Management">the wiki page</a> for more information on how to set this up.
</xsl:if>

<xsl:if test="count(cdash/os)>0">
  <xsl:if test="count(cdash/project/repository)>0">
    <form method="post" action="">
    <table id="form_table">
      <tr>
        <td align="right"><b>Project:</b></td>
        <td><xsl:value-of select="cdash/project/name" /></td>
      </tr>
      <tr>
        <td align="right" valign="top">
          <b>Repository:</b>
        </td>
        <td>
         <select name="repository" id="repository_select">
          <xsl:for-each select="/cdash/project/repository">
              <option>
                <xsl:attribute name="value"><xsl:value-of select="url"/></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>
                <xsl:value-of select="url"/>
              </option>
          </xsl:for-each>
          </select>
        </td>
      </tr>
      <tr>
        <td align="right" valign="top">
          <b>Alternative Repository:</b>
         </td>
         <td>
           <input name="otherrepository" type="text" size="60">
           <xsl:attribute name="value"><xsl:value-of select="/cdash/otherrepository"/></xsl:attribute>
           </input>
         </td>
      </tr>
      <tr>
        <td align="right" valign="top">
          <b>Module/Branch:</b>
         </td>
         <td>
           <input name="module" type="text" size="60">
           <xsl:attribute name="value"><xsl:value-of select="/cdash/module"/></xsl:attribute>
           </input>
         </td>
      </tr>
      <tr>
        <td align="right" valign="top">
          <b>Tag:</b>
         </td>
         <td>
           <input name="tag" type="text" size="60">
           <xsl:attribute name="value"><xsl:value-of select="/cdash/tag"/></xsl:attribute>
           </input>
         </td>
      </tr>
      <tr>
        <td align="right" valign="top">
          <b>BuildName Suffix:</b>
         </td>
         <td>
           <input name="buildnamesuffix" type="text" size="60">
           <xsl:attribute name="value"><xsl:value-of select="/cdash/buildnamesuffix"/></xsl:attribute>
           </input>
         </td>
      </tr>
      <tr>
        <td valign="top" align="right"><b>Operating System:</b><br/><a href="#" onclick="return clearOS();">[clear all]</a></td>
        <td>
          <select multiple="multiple" name="system[]" id="system_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/os">
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>
                <xsl:value-of select="name"/>
              </option>
            </xsl:for-each>
          </select>
        </td>
      </tr>
      <tr>
        <td valign="top" align="right"><b>Compiler:</b><br/><a href="#" onclick="return clearCompiler();">[clear all]</a></td>
        <td>
          <select multiple="multiple" name="compiler[]" id="compiler_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/compiler">
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>
                <xsl:value-of select="name"/>
              </option>
            </xsl:for-each>
          </select>
        </td>
      </tr>
      <tr>
        <td valign="top" align="right"><b>CMake:</b><br/><a href="#" onclick="return clearCMake()">[clear all]</a></td>
        <td>
          <select multiple="multiple" name="cmake[]" id="cmake_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/cmake">
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>
                <xsl:value-of select="version"/>
              </option>
            </xsl:for-each>
          </select>
        </td>
      </tr>
      <tr>
        <td valign="top" align="right"><b>Libraries:</b><br/><a href="#" onclick="return clearLibrary()">[clear all]</a></td>
        <td>
          <select multiple="multiple" name="library[]" id="library_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/library">
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>
                <xsl:value-of select="name"/>
              </option>
            </xsl:for-each>
          </select>
        </td>
      </tr>
      <tr>
        <td valign="top" align="right"><b>Site:</b><br/><a href="#" onclick="return clearSite()">[clear all]</a></td>
        <td>
          <select multiple="multiple" name="site[]" id="site_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/site">
              <option>
                <xsl:if test="availablenow=0"><xsl:attribute name="style">color:red</xsl:attribute></xsl:if>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>
                <xsl:value-of select="name"/>
              </option>
            </xsl:for-each>
          </select><br/>
        Sites marked in <font color="red">red</font> have not been responding in the last 5 minutes.
        <div id="check"></div>
        <br/>
        </td>
      </tr>

      <tr>
        <td align="right" valign="top"><b>Initial CMakeCache:</b></td>
        <td><textarea style="width:600px" rows="4" id="cmakecache" name="cmakecache"><xsl:value-of select="/cdash/cmakecache"/></textarea></td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Job-specific client script:</b></td>
        <td><textarea style="width:600px" rows="10" id="clientscript" name="clientscript"><xsl:value-of select="/cdash/clientscript"/></textarea></td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Type:</b></td>
        <td><select name="type">
             <option value="0"><xsl:if test="/cdash/type=0"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>Experimental</option>
             <option value="1"><xsl:if test="/cdash/type=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>Nightly</option>
             <option value="2"><xsl:if test="/cdash/type=2"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>Continuous</option>
          </select>
          </td>
      </tr>
      <tr>
        <td align="right" valign="top">
          <b>Build Configuration:</b>
        </td>
        <td>
         <select name="buildconfiguration" id="buildconfiguration_select">
          <xsl:for-each select="/cdash/buildconfiguration">
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>
                <xsl:value-of select="name"/>
              </option>
          </xsl:for-each>
          </select>
        </td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Start date:</b></td>
        <td><input name="startdate" type="text" size="19" maxlength="19">
        <xsl:attribute name="value"><xsl:value-of select="/cdash/startdate" /></xsl:attribute>
        </input></td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>End date:</b></td>
        <td><input name="enddate" type="text" size="19" maxlength="19">
         <xsl:attribute name="value"><xsl:value-of select="/cdash/enddate" /></xsl:attribute>
        </input></td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Start time:</b></td>
        <td><input name="starttime" type="text" size="8" maxlength="8">
         <xsl:attribute name="value"><xsl:value-of select="/cdash/starttime" /></xsl:attribute>
         </input>
        </td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Repeat every:</b></td>
        <td><input name="repeat" type="text" size="4" maxlength="4">
        <xsl:attribute name="value"><xsl:value-of select="/cdash/repeat" /></xsl:attribute>
        </input>
        hour(s)</td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Enable:</b></td>
        <td><input name="enable" type="checkbox">
        <xsl:if test="/cdash/enable=1">
        <xsl:attribute name="checked">checked</xsl:attribute>
        </xsl:if>
        </input>
        </td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Description:</b></td>
        <td><input maxlength="255" id="description" name="description" size="100">
          <xsl:attribute name="value"><xsl:value-of select="/cdash/description"/></xsl:attribute>
          </input></td>
      </tr>
      <tr>
        <td></td>
        <td>
        <xsl:choose>
        <xsl:when test="/cdash/edit=1"><input name="update" type="submit" value="Update Schedule >>" /></xsl:when>
        <xsl:otherwise><input name="submit" type="submit" value="Schedule >>" /></xsl:otherwise>
        </xsl:choose>
        </td>
      </tr>
    </table>
    </form>
  </xsl:if>
</xsl:if>

<div id="result" style="display:none;">
<img src="img/loading.gif" /></div>

<!--
<xsl:if test="/cdash/edit=1">
  <hr/>
  <h3>Builds Submitted For This Job</h3>
  <xsl:for-each select="/cdash/build">
    <a><xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="id" /></xsl:attribute><xsl:value-of select="id" /></a><br />
  </xsl:for-each>
</xsl:if>
-->

<!-- FOOTER -->
<br/><br/>
<xsl:call-template name="footer"/>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
