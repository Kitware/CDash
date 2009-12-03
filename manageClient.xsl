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
<h3>Setup a new build</h3>

<xsl:if test="count(cdash/project/repository)=0">
  You should set the <a>
  <xsl:attribute name="href">
    createProject.php?edit=1&#38;projectid=<xsl:value-of select="cdash/project/id" />#fragment-3
  </xsl:attribute>
  project repository</a> before starting.<br/>
</xsl:if>  
<xsl:if test="count(cdash/system)=0">
No site are currently available. You should run the CTest script in order to register a couple of clients.
</xsl:if>   

<xsl:if test="count(cdash/system)>0">
  <xsl:if test="count(cdash/project/repository)>0">
    <form method="post" action="">
    <table id="form_table">
      <tr>
        <td align="right"><b>Project:</b></td>
        <td><xsl:value-of select="cdash/project/name" /></td>    
      </tr>
      <tr>
        <td align="right" valign="top"><xsl:if test="count(cdash/project/repository)=1">
          <b>Repository:</b>
        </xsl:if>   
        <xsl:if test="count(cdash/project/repository)>1">
          <b>Repositories:</b>
        </xsl:if>   
        </td>
        <td>
          <xsl:for-each select="/cdash/project/repository">
            <xsl:value-of select="url"/><br/>
          </xsl:for-each>
        </td>
      </tr>
      <tr>
        <td align="right"><b>Operating System:</b></td>
        <td>
          <select name="system" id="system_select" onchange="changeCompiler();">
            <option>Select an Operating System </option>
            <xsl:for-each select="/cdash/system">              
              <option>
                <xsl:attribute name="value"><xsl:value-of select="osid" /></xsl:attribute>
                <xsl:value-of select="os"/>
              </option>
            </xsl:for-each>
          </select>
        </td>
      </tr>
      
      <tr id="result_compiler">
        <td align="right" id="result_compiler1"></td>
        <td id="result_compiler2"></td>
      </tr>
      <tr id="result_cmake">
        <td align="right" id="result_cmake1"></td>
        <td id="result_cmake2"></td>
      </tr>
      <tr id="result_library">
        <td align="right" valign="top" id="result_library1"></td>
        <td id="result_library2"></td>
      </tr>
      <tr id="result_toolkit">
        <td align="right" valign="top" id="result_toolkit1"></td>
        <td id="result_toolkit2"></td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Initial CMakeCache:</b></td> 
        <td><textarea style="width:600px;height:200px;" id="cache_text" name="cache"></textarea></td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Type:</b></td> 
        <td><select name="type">
            <option value="0">Experimental</option>
             <option value="1">Nightly</option>
             <option value="2">Continuous</option>
          </select>
          </td>
      </tr>
      <tr>
        <td align="right" valign="top"><b>Interval:</b></td> 
        <td><input name="interval" type="text" size="2" maxlength="2" value="0"/></td>
      </tr>
      <tr>
        <td></td>
        <td><input name="submit" type="submit" value="Schedule >>" /></td>
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
