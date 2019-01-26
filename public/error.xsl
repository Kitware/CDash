<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
    <xsl:include href="logout.xsl"/>

   <!-- Include local common files -->
   <xsl:include href="local/header.xsl"/>
   <xsl:include href="local/footer.xsl"/>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>

    <xsl:template match="/">
       <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
          <link rel="shortcut icon" href="favicon.ico"/>
           <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
        <script src="js/jquery-1.6.2.js" type="text/javascript" charset="utf-8"></script>
        <link type="text/css" rel="stylesheet" href="css/jquery.qtip.min.css" />
        <script src="js/jquery.qtip.min.js" type="text/javascript" charset="utf-8"></script>

        <!-- Include the sorting -->
        <script src="js/jquery.cookie.js" type="text/javascript" charset="utf-8"></script>
        <script src="js/jquery.tablesorter.js" type="text/javascript" charset="utf-8"></script>

        <!-- include jqModal -->
        <script src="js/jqModal.js" type="text/javascript" charset="utf-8"></script>
        <link type="text/css" rel="stylesheet" media="all" href="css/jqModal.css" />

        <script src="js/cdashTableSorter.js" type="text/javascript" charset="utf-8"></script>
        <script src="js/cdashIndexTable.js" type="text/javascript" charset="utf-8"></script>
        <xsl:if test="/cdash/uselocaldirectory=1">
            <link type="text/css" rel="stylesheet" href="local/cdash.local.css" />
        </xsl:if>
       </head>
       <body>

 <div id="header">
 <div id="headertop">
  <div id="topmenu">
     <xsl:choose>
        <xsl:when test="cdash/user/id>0">
         <a href="user.php">My CDash</a>
        </xsl:when>
        <xsl:otherwise><a href="login">Login</a> <a href="register">Register</a></xsl:otherwise>
     </xsl:choose>
     <xsl:if test="cdash/user/id>0">
       <xsl:call-template name="logout"/>
     </xsl:if>
  </div>
 </div>

 <div id="headerbottom">
    <div id="headerlogo">
      <a>
        <xsl:attribute name="href">
        <xsl:value-of select="cdash/dashboard/home"/></xsl:attribute>
        <img id="projectlogo" border="0" height="50px">
        <xsl:attribute name="alt"></xsl:attribute>
        <xsl:choose>
        <xsl:when test="cdash/dashboard/logoid>0">
          <xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of select="cdash/dashboard/logoid"/></xsl:attribute>
         </xsl:when>
        <xsl:otherwise>
         <xsl:attribute name="src">img/cdash.png?rev=2019-05-08</xsl:attribute>
        </xsl:otherwise>
        </xsl:choose>
        </img>
      </a>
    </div>
    <div id="headername2">
      <span id="subheadername">
        <xsl:value-of select="cdash/dashboard/title"/> <xsl:value-of select="cdash/dashboard/subtitle"/>
      </span>
    </div>
 </div>
</div>


<!-- Main table -->
<br/>

<xsl:if test="string-length(cdash/upgradewarning)>0">
  <p style="color:red"><b>The current database schema doesn't match the version of CDash you are running,
    upgrade your database structure in the <a href="upgrade.php">Administration/CDash maintenance panel of CDash</a></b></p>
</xsl:if>

<xsl:value-of select="cdash/error"/><br/>
<a href="index.php">Go to the list of dashboards</a>
<br/>

<!-- FOOTER -->
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
