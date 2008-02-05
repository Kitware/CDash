<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
        
   <!--  <xsl:output method="html"/> -->
    <xsl:output method="xml" doctype-public="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>
				<xsl:template name="headerback" match="/">
    
     <link rel="shortcut icon" href="favicon.ico"/>	

<table width="100%" class="toptable" cellpadding="1" cellspacing="0">
  <tr>
    <td>
		<table width="100%" align="center" cellpadding="0" cellspacing="0" >
  <tr>
    <td height="22" class="topline"><xsl:text>&#160;</xsl:text></td>
  </tr>
  <tr>
    <td width="100%" align="left" class="topbg">

		  <table width="100%" height="121" border="0" cellpadding="0" cellspacing="0" >
	   <tr>
		  <td width="195" height="121" class="topbgleft">
					<xsl:text>&#160;</xsl:text> <img  border="0" alt="" src="images/cdash.gif"/>
				</td>
				<td width="425" valign="top" class="insd">
				<div class="insdd">
						<span class="inn1"><xsl:value-of select="/cdash/menutitle"/></span><br />
						<span class="inn2"><xsl:value-of select="/cdash/menusubtitle"/></span>
						</div>
				</td>
				<td height="121" class="insd2"><xsl:text>&#160;</xsl:text></td>
			</tr>
		</table>

		</td>
				</tr>
  <tr>
    <td align="left" class="topbg2"><table width="100%" height="28" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td width="631" align="left" class="bgtm"><ul id="Nav" class="nav">
<li id="Dartboard">
<a><xsl:attribute name="href"><xsl:value-of select="/cdash/backurl"/></xsl:attribute>BACK</a><ul>
</ul>
</li>
</ul>
</td>
<span id="calendar" class="cal"></span>
		<td height="28" class="insd3"><xsl:text>&#160;</xsl:text></td>
	</tr>
</table></td>
  </tr>
</table></td>
  </tr>
</table>


<!--  OLD XSL!
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php">
<img  border="0">
<xsl:attribute name="alt"><xsl:value-of select="cdash/dashboard/projectname"/></xsl:attribute>
<xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of select="cdash/dashboard/logoid"/></xsl:attribute>
</img>
</a>
</td>
<td valign="bottom" width="100%">
<div style="margin: 0pt auto; background-color: #6699cc;"  class="rounded">    
<font color="#ffffff"><h2>Dashboard - <xsl:value-of select="cdash/dashboard/projectname"/></h2>
<h3><xsl:value-of select="cdash/dashboard/datetime"/></h3></font>
<div align="right"><a> 
 <xsl:attribute name="href">rss/SubmissionRSS<xsl:value-of select="cdash/dashboard/projectname"/>.xml</xsl:attribute>
<img src="images/feed-icon16x16.png" border="0"/></a></div>
</div>
</td>

</tr>
<tr>
<td></td>
<td>
<ul id="Nav" class="nav">
      <li id="Dartboard">
        <a href="index.php">Dartboard</a>
        <ul>
          <li><a href="#Updates">Updates</a></li>
     <li><a><xsl:attribute name="href">testOverview.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>Tests</a></li>
          <li><a href="#Build">Build</a></li>
     <li><a><xsl:attribute name="href">viewMap.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>Map</a></li>
        </ul>
      </li>
        <li>
        <a id="cal" href="#">Calendar</a> 
    </li>
      <li>
         <a>
  <xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/previousdate"/></xsl:attribute>
  Previous
  </a>
    </li>
      <li>
        <a>
    <xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/></xsl:attribute>
    Today
    </a>
    </li>
          <li>
         <a vertical-align="middle">
            <xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/nextdate"/></xsl:attribute>
      Next
      </a>
    </li>
      <li>
        <a href="#">Project</a>
        <ul>
         <li><a><xsl:attribute name="href">http://<xsl:value-of select="cdash/dashboard/home"/> </xsl:attribute>Home</a></li>
          <li><a><xsl:attribute name="href">http://<xsl:value-of select="cdash/dashboard/svn"/> </xsl:attribute>CVS</a></li>
          <li><a><xsl:attribute name="href">http://<xsl:value-of select="cdash/dashboard/bugtracker"/> </xsl:attribute>Bugs</a></li>
        </ul>
      </li>
    <li>
        <a><xsl:attribute name="href">user.php</xsl:attribute>
         <xsl:choose>
          <xsl:when test="cdash/user/id>0">
            My CDash    
          </xsl:when>
          <xsl:otherwise>
             Login
           </xsl:otherwise>
        </xsl:choose>  
        </a>
      </li>
    </ul>
    <span id="calendar" class="cal"></span>
</td>
</tr>
</table>
-->

    </xsl:template>
</xsl:stylesheet>
