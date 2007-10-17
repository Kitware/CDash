<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    				
    <xsl:output method="html"/>
    <xsl:template name="header" match="/">
			<script type="text/javascript" SRC="javascript/calendar.js"></script>
			<div id="calendar" style="visibility:hidden">

			 <form name="dartForm">
				<input type="hidden" name="dartDateField"/>
				</form>
				<script type="text/javascript" SRC="javascript/DashboardMap.js"></script>
				</div>
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php">
<img  border="0">
<xsl:attribute name="alt"><xsl:value-of select="cdash/dashboard/projectname"/></xsl:attribute>
<xsl:attribute name="src">displayLogo.php?projectid=<xsl:value-of select="cdash/dashboard/projectid"/></xsl:attribute>
</img>
</a>
</td>
<td bgcolor="#6699cc" valign="bottom" width="100%">
<font color="#ffffff"><h2>Dashboard - <xsl:value-of select="cdash/dashboard/datetime"/></h2>
<h3><xsl:value-of select="cdash/dashboard/date"/></h3></font>
<div align="right"><a> 
	<xsl:attribute name="href">rss/SubmissionRSS<xsl:value-of select="cdash/dashboard/projectname"/>.xml</xsl:attribute>
<img src="images/feed-icon16x16.png" border="0"/></a></div>
</td>
</tr>
<tr>
<td></td>
<td>
<div id="navigator">
<table border="0" cellpadding="0" cellspacing="0">
<tr>
	     <td align="center">

<p class="darthoverbutton">
 <a>
		<xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/></xsl:attribute>
		Dartboard
		</a>
</p>
</td>

	<td align="center">
<p class="darthoverbutton">
<a href="javascript:dartCalendar()" onClick="document.dartForm.dartDateField.value='Wednesday, October 10 2007'; setDateField(document.dartForm.dartDateField); top.newWin = window.open('javascript/calendar.html', 'cal', 'dependent=yes,resizable=yes,width=210,height=230,screenX=200,screenY=300,titlebar=yes,scrollbar=auto');">Date<img
BORDER="0" ALIGN="ABSMIDDLE" src="images/Calendar.gif"/></a>
</p>
</td>

	  <td align="center">
<p class="smalldarthoverbutton">
		<a>
		<xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/previousdate"/></xsl:attribute>		
		<img HEIGHT="16" BORDER="0" ALIGN="ABSMIDDLE" alt="Previous Day" src="images/LeftBlack.gif"/>
		</a>
	     </p>
	  </td>
	
	  <td align="center">
<p class="smalldarthoverbutton">
	       <a>
								<xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/></xsl:attribute>
								T
								</a>
	    </p>
	  </td>
	
	    <td align="center">

<p class="smalldarthoverbutton">
		    <a >
						<xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/nextdate"/></xsl:attribute>
						<img HEIGHT="16" BORDER="0" ALIGN="ABSMIDDLE" alt="Next Day" src="images/RightBlack.gif"/>
						</a>
	     </p>
	    </td>

<td align="center">
<p class="darthoverbutton">
<a href="../20071010-0100-Nightly/Update.html">Updates</a>
</p>
</td>
<td align="center">

<p class="darthoverbutton">
<a href="../20071010-0100-Nightly/TestOverviewByCount.html">Tests</a>
</p>
</td>
<td align="center">
<p class="darthoverbutton">
<a href="../20071010-0100-Nightly/BuildOverview.html">Build</a>
</p>
</td>

		<td align="center">
<p class="hoverbutton">
<a><xsl:attribute name="href">http://<xsl:value-of select="cdash/dashboard/svn"/> </xsl:attribute>CVS</a>

</p>
</td>

<td align="center">
<p class="hoverbutton">
<a><xsl:attribute name="href">http://<xsl:value-of select="cdash/dashboard/bugtracker"/> </xsl:attribute>Bugs</a>
</p>
</td>

<td align="center">
<p class="hoverbutton">
<a><xsl:attribute name="href">http://<xsl:value-of select="cdash/dashboard/home"/> </xsl:attribute>Home</a>
</p>
</td>

<td align="center">
<p class="hoverbutton">
<a><xsl:attribute name="href">user.php</xsl:attribute>Login</a>
</p>
</td>

</tr>
</table>
</div>
</td>
</tr>
</table>
    </xsl:template>
</xsl:stylesheet>
