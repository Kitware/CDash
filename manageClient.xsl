<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="footer.xsl"/>
  <xsl:include href="headscripts.xsl"/>
  <xsl:include href="headeradminproject.xsl" />
  
  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>
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
        <script src="javascript/cdashClient.js" type="text/javascript" charset="utf-8"></script>
      </head>

 <body>
 <xsl:call-template name="headeradminproject" />

<!-- Message -->
<div style="color: green;"><xsl:value-of select="cdash/message" /></div>
<h3>Schedule a build</h3>

<xsl:if test="count(cdash/project/repository)=0">
  You should set the <a>
  <xsl:attribute name="href">
    createProject.php?edit=1&#38;projectid=<xsl:value-of select="cdash/project/id" />#fragment-3
  </xsl:attribute>
  project repository</a> before starting.<br/>
</xsl:if>  
<xsl:if test="count(cdash/os)=0">
No site are currently available. You should run the CTest script in order to register a couple of clients.
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
                <xsl:if test="selected=1"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>
                <xsl:value-of select="url"/>
              </option>
          </xsl:for-each>
          </select>
        </td>
      </tr>
      <tr>
        <td align="right" valign="top">
          <b>Other Repository:</b>
         </td>
         <td>
           <input name="otherrepository" type="text" size="60">
           <xsl:attribute name="value"><xsl:value-of select="/cdash/otherrepository"/></xsl:attribute>
           </input>
         </td>
      </tr>
      <tr>
        <td align="right" valign="top">
          <b>CVS Module:</b>
         </td>
         <td>
           <input name="module" type="text" size="60">
           <xsl:attribute name="value"><xsl:value-of select="/cdash/module"/></xsl:attribute>
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
        <td valign="top" align="right"><b>Operating System:</b><br/><a href="#" onclick="clearOS()">[clear all]</a></td>
        <td>
          <select multiple="true" name="system[]" id="system_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/os">             
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>
                <xsl:value-of select="name"/>
              </option>
            </xsl:for-each>
          </select>
        </td>
      </tr>
      <tr>
        <td valign="top" align="right"><b>Compiler:</b><br/><a href="#" onclick="clearCompiler()">[clear all]</a></td>
        <td>
          <select multiple="true" name="compiler[]" id="compiler_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/compiler">              
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>
                <xsl:value-of select="name"/>
              </option>
            </xsl:for-each>
          </select>
        </td>
      </tr> 
      <tr>
        <td valign="top" align="right"><b>CMake:</b><br/><a href="#" onclick="clearCMake()">[clear all]</a></td>
        <td>
          <select multiple="true" name="cmake[]" id="cmake_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/cmake">              
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>
                <xsl:value-of select="version"/>
              </option>
            </xsl:for-each>
          </select>
        </td>
      </tr> 
      <tr>
        <td valign="top" align="right"><b>Libraries:</b><br/><a href="#" onclick="clearLibrary()">[clear all]</a></td>
        <td>
          <select multiple="true" name="library[]" id="library_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/library">              
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>
                <xsl:value-of select="name"/>
              </option>
            </xsl:for-each>
          </select>
        </td>
      </tr>
      <tr>
        <td valign="top" align="right"><b>Toolkits:</b><br/><a href="#" onclick="clearToolkit()">[clear all]</a></td>
        <td>
          <select multiple="true" name="toolkitconfiguration[]" id="toolkit_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/toolkit">              
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>
                <xsl:value-of select="name"/>
              </option>
            </xsl:for-each>
          </select>
        </td>
      </tr>
      <tr>
        <td valign="top" align="right"><b>Site:</b><br/><a href="#" onclick="clearSite()">[clear all]</a></td>
        <td>
          <select multiple="true" name="site[]" id="site_select" onchange="checkSystem();">
            <xsl:for-each select="/cdash/site">              
              <option>
                <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
                <xsl:if test="selected=1"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>
                <xsl:value-of select="name"/>
              </option>
            </xsl:for-each>
          </select>
        <div id="check"></div>  
        </td>
      </tr>
       
      <tr>
        <td align="right" valign="top"><b>Initial CMakeCache:</b></td> 
        <td><textarea style="width:600px" rows="4" id="cmakecache" name="cmakecache"><xsl:value-of select="/cdash/cmakecache"/></textarea></td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Type:</b></td> 
        <td><select name="type">
             <option value="0"><xsl:if test="/cdash/type=0"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>Experimental</option>
             <option value="1"><xsl:if test="/cdash/type=1"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>Nightly</option>
             <option value="2"><xsl:if test="/cdash/type=2"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>Continuous</option>
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
        <xsl:attribute name="checked">true</xsl:attribute>
        </xsl:if>
        </input>
        </td>
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
<img src="images/loading.gif" /></div>

<!-- FOOTER -->
<br/><br/>
<xsl:call-template name="footer"/>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
