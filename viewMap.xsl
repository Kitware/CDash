<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
       <xsl:include href="footer.xsl"/>
							
 <!-- HEADER -->  
  <xsl:output method="xml" doctype-public="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>
			 <xsl:output method="html" encoding="iso-8859-1"/>  
    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
				 <link rel="shortcut icon" href="favicon.ico"/>	
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
       </head>
       <body>
														
							<table width="100%" class="toptable" cellpadding="1" cellspacing="0">
  <tr>
    <td>
		<table width="100%" align="center" cellpadding="0" cellspacing="0" >
  <tr>
    <td height="30" valign="middle">
				<table width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td width="66%" class="paddl">
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
								
								<xsl:if test="cdash/user/id>0">
								  <xsl:text>&#160;</xsl:text>|<xsl:text>&#160;</xsl:text><a href="user.php?logout=1">Log Out</a>  
								</xsl:if>
								
								</td>
        <td width="34%" class="topdate">
								  <span style="float:right">
									<xsl:text>&#160;</xsl:text>
	        </span>
									<xsl:value-of select="cdash/dashboard/datetime"/>
	     </td>
      </tr>
    </table>    
				</td>
  </tr>
  <tr>
    <td height="22" class="topline"><xsl:text>&#160;</xsl:text></td>
  </tr>
  <tr>
    <td width="100%" align="left" class="topbg">

		  <table width="100%" height="121" border="0" cellpadding="0" cellspacing="0" >
	   <tr>
		  <td width="195" height="121" class="topbgleft">
				</td>
				<td width="425" valign="top" class="insd">
				<div class="insdd">
						<span class="inn1">CDash</span><br />
						<span class="inn2">Build location</span>
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
<a href="index.php">HOME</a>
</li>
<li><a><xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>PROJECT</a></li>
</ul>
</td>
		<td height="28" class="insd3"><xsl:text>&#160;</xsl:text></td>
	</tr>
</table></td>
  </tr>
</table></td>
  </tr>
</table>

<!--
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php"><img alt="Logo/Homepage link" height="100" src="images/cdash.gif" border="0"/></a>
</td>
<td bgcolor="#6699cc" valign="top" width="100%">
<font color="#ffffff"><h2>CDash - Sites Map for <xsl:value-of select="cdash/dashboard/projectname"/></h2>
<h3>Where are the builds located?</h3></font>
</td></tr><tr><td></td><td>
<ul id="Nav" class="nav">
   <li>
        <a><xsl:attribute name="href">index.php</xsl:attribute>Home</a>
      </li>
			   <li>
        <a><xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>Project</a>
      </li>		
</ul>
</td>
</tr>
</table>
-->

<br/>

<!-- Display the map --> 
  <script type="text/javascript">
      <xsl:attribute name="src">http://maps.google.com/maps?file=api&amp;v=2&amp;key=<xsl:value-of select="cdash/dashboard/googlemapkey"/></xsl:attribute>
   </script>
    <script type="text/javascript">
      // Creates a marker whose info window displays the letter corresponding
      // to the given index.
      function createMarker(point,title) 
        {     
        var marker = new GMarker(point);
        GEvent.addListener(marker, "click", function() 
          {
          marker.openInfoWindowHtml(title);
          });
        return marker;
      }

    function load() {
      if (GBrowserIsCompatible()) {
        var map = new GMap2(document.getElementById("map"));
        map.setCenter(new GLatLng(37.4419, -30.00), 2);
        map.addControl(new GLargeMapControl());
        <xsl:for-each select="cdash/site">
        <xsl:if test="string-length(latitude)>0">
        var point = new GLatLng(<xsl:value-of select="latitude"/>,<xsl:value-of select="longitude"/>);
        map.addOverlay(createMarker(point,'<xsl:value-of select="name"/>'));
        </xsl:if>
        </xsl:for-each>
      }
    }
    </script>
   <body onload="load()" onunload="GUnload()">
  <center><div id="map" style="width: 700px; height: 400px"></div></center>
  </body>

<script type="text/javascript">
  Rounded('rounded', 15, 15,0,0);
</script>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
