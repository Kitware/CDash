<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
     
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
   
   <xsl:include href="local/header.xsl"/>
   <xsl:include href="local/footer.xsl"/> 
      
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
         <xsl:call-template name="headscripts"/> 
       </head>
       <body bgcolor="#ffffff">
   
<xsl:choose>         
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="header_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="header"/>
</xsl:otherwise>
</xsl:choose>

<br/>

<table border="0">
<tr><td align="left"><b>Site: </b><xsl:value-of select="cdash/build/site"/></td></tr>
<tr><td align="left"><b>Build Name: </b><xsl:value-of select="cdash/build/buildname"/></td></tr>
<tr><td align="left"><b>Build Time: </b><xsl:value-of select="cdash/build/starttime"/></td></tr>
<tr><td align="left">&#x20;</td></tr>
<tr><td align="left">Found <b><xsl:value-of select="count(cdash/errors/error)"/></b><xsl:text>&#x20;</xsl:text><xsl:value-of select="cdash/errortypename"/>s</td></tr>
<tr><td align="left"><a>
<xsl:attribute name="href">viewBuildError.php?type=<xsl:value-of select="cdash/nonerrortype"/>&#38;buildid=<xsl:value-of select="cdash/build/buildid"/></xsl:attribute>
<xsl:value-of select="cdash/nonerrortypename"/>s</a> are here.</td></tr>
</table>

<xsl:for-each select="cdash/errors/error">
<br/>
<table width="100%">

<xsl:if test="sourceline">
<tr style="background-color: #b0c4de; font-weight: bold">
<th colspan="2" align="left">
<pre> </pre>
</th>
</tr>
</xsl:if>

<!--
<div style="margin-left: 200px">
<xsl:if test="sourceline>0">
<xsl:value-of select="/cdash/errortypename"/> while building file <xsl:value-of select="sourcefile"/>
at line <xsl:value-of select="sourceline"/>
</xsl:if>
<xsl:if test="sourceline=0">
<xsl:value-of select="/cdash/errortypename"/>
</xsl:if>
</div>
-->

<xsl:if test="targetname">
<tr style="background-color: #b0c4de; font-weight: bold">
<th colspan="2">
  <xsl:value-of select="/cdash/errortypename"/> while building
  <code><xsl:value-of select="language"/></code>
  <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
  <xsl:value-of select="outputtype"/>
  <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
  "<code><xsl:value-of select="outputfile"/></code>"
  in target <code><xsl:value-of select="targetname"/></code>.
</th>
</tr>
</xsl:if>

<xsl:if test="string-length(cvsurl) > 0">
<tr>
<th class="measurement"><nobr> CVS/SVN </nobr></th>
<td>
<a>
  <xsl:attribute name="href">
    <xsl:value-of select="cvsurl"/>
  </xsl:attribute>
  <xsl:value-of select="cvsurl"/>
</a>
</td>
</tr>
</xsl:if>

<xsl:if test="logline">
<tr>
<th class="measurement"><nobr> Build Log Line </nobr></th>
<td>
<xsl:value-of select="logline"/>
</td>
</tr>
</xsl:if>

<xsl:if test="precontext or text or postcontext">
<tr>
<th class="measurement"><nobr> <xsl:value-of select="/cdash/errortypename"/> </nobr></th>
<td>
<pre><xsl:value-of select="precontext"/></pre>
<b><pre><xsl:value-of select="text"/></pre></b>
<pre><xsl:value-of select="postcontext"/></pre>
</td>
</tr>
</xsl:if>

<xsl:if test="string-length(sourcefile)>0 and targetname">
<tr>
<th class="measurement"><nobr>Source File</nobr></th><td><xsl:value-of select="sourcefile"/></td>
</tr>
</xsl:if>

<xsl:if test="labels/label">
<tr>
<th class="measurement">
<xsl:if test="count(labels/label) = 1"><nobr>Label</nobr></xsl:if>
<xsl:if test="count(labels/label) > 1"><nobr>Labels</nobr></xsl:if>
</th>
<td>
<xsl:for-each select="labels/label">
<xsl:if test="position() > 1">,
<xsl:text disable-output-escaping="yes"> </xsl:text>
</xsl:if>
<nobr><xsl:value-of select="."/></nobr>
</xsl:for-each>
</td>
</tr>
</xsl:if>

<xsl:if test="argument">
<tr>
<th class="measurement" style="width: 1%">Command</th>
<td>
<div style="margin-left: 25px; text-indent: -25px;">
<xsl:for-each select="argument">
<nobr>"<font class="argument"><xsl:value-of select="."/></font>"</nobr><xsl:text disable-output-escaping="yes"> </xsl:text>
</xsl:for-each>
</div>
</td>
</tr>
</xsl:if>

<xsl:if test="workingdirectory">
<tr>
<th class="measurement" style="width: 1%">Directory</th><td><xsl:value-of select="workingdirectory"/></td>
</tr>
</xsl:if>

<xsl:if test="exitcondition">
<tr>
<th class="measurement"><nobr>Exit Condition</nobr></th><td><xsl:value-of select="exitcondition"/></td>
</tr>
</xsl:if>

<xsl:if test="stdoutput">
<tr>
<th class="measurement"><nobr> Standard Output </nobr></th>
<td>
<textarea readonly="readonly" name="stdout" wrap="off" style="width: 100%">
  <xsl:attribute name="rows"><xsl:value-of select="stdoutputrows"/></xsl:attribute>
<xsl:value-of select="stdoutput"/>
</textarea>
</td>
</tr>
</xsl:if>

<xsl:if test="stderror">
<tr>
<th class="measurement"><nobr>Standard Error</nobr></th>
<td>
<textarea readonly="readonly" name="stderr" wrap="off" style="width: 100%">
  <xsl:attribute name="rows"><xsl:value-of select="stderrorrows"/></xsl:attribute>
<xsl:value-of select="stderror"/>
</textarea>
</td>
</tr>
</xsl:if>

</table>
</xsl:for-each>
<br/>
<!-- FOOTER -->
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
