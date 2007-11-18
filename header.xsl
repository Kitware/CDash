<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
        
    <xsl:output method="html"/>
    <xsl:template name="headerhead" match="/">
  		
		<!-- Include JQuery -->
		<script src="javascript/jquery.js" type="text/javascript" charset="utf-8"></script>	
	
	 <!-- Include Menu JavaScript -->
	 <script src='javascript/menu.js' type='text/javascript'></script>
			
			<!-- Include Core Datepicker JavaScript -->
		<script src="javascript/ui.datepicker.js" type="text/javascript" charset="utf-8"></script>	
		
		<!-- Include Calendar JavaScript -->
		<script src="javascript/cdashmenu.js" type="text/javascript" charset="utf-8"></script>
		
			<!-- Include Core Datepicker Stylesheet -->		
		<link rel="stylesheet" href="javascript/ui.datepicker.css" type="text/css" media="screen" title="core css file" charset="utf-8" />
		 <!-- Include CDash Menu Stylesheet -->		
		<link rel="stylesheet" href="javascript/cdashmenu.css" type="text/css" media="screen" charset="utf-8" />
		
		<!-- Include the rounding css -->
		<script src="javascript/rounded.js"></script>
</xsl:template>

<xsl:template name="header" match="/">

<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php">
<img  border="0">
<xsl:attribute name="alt"><xsl:value-of select="cdash/dashboard/projectname"/></xsl:attribute>
<xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of select="cdash/dashboard/logoid"/></xsl:attribute>
</img>
</a>
</td>
<td id="myid" valign="bottom" width="100%" class="rounded">
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
					<li><a href="#Tests">Tests</a></li>
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
				<a><xsl:attribute name="href">user.php</xsl:attribute>Login</a>
			</li>
		</ul>
		<span id="calendar" class="cal"></span>
		
		
		
	<!--	
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
<a><xsl:attribute name="href">viewMap.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>Map</a>
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
-->
</td>
</tr>
</table>

		<script type="text/javascript">
Rounded('rounded', 15, 15,0,0);
</script>

    </xsl:template>
</xsl:stylesheet>
