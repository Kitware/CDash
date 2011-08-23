<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>

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
        <script src="javascript/jquery-1.6.2.js" type="text/javascript" charset="utf-8"></script>
        <link type="text/css" rel="stylesheet" href="javascript/jquery.qtip.min.css" />
        <script src="javascript/jquery.qtip.min.js" type="text/javascript" charset="utf-8"></script>

        <!-- Include the sorting -->
        <script src="javascript/jquery.cookie.js" type="text/javascript" charset="utf-8"></script>
        <script src="javascript/jquery.tablesorter.js" type="text/javascript" charset="utf-8"></script>

        <!-- include jqModal -->
        <script src="javascript/jqModal.js" type="text/javascript" charset="utf-8"></script>
        <link type="text/css" rel="stylesheet" media="all" href="javascript/jqModal.css" />

        <script src="javascript/cdashTableSorter.js" type="text/javascript" charset="utf-8"></script>
        <script src="javascript/cdashIndexTable.js" type="text/javascript" charset="utf-8"></script>
       </head>
       <body>

 <div id="header">
 <div id="headertop">
  <div id="topmenu">
    <a><xsl:attribute name="href">user.php</xsl:attribute>
        <xsl:choose>
          <xsl:when test="cdash/user/id>0">My CDash</xsl:when>
          <xsl:otherwise>Login <a href="register.php">Register</a></xsl:otherwise>
        </xsl:choose></a>
     <xsl:if test="cdash/user/id>0">
       <a href="user.php?logout=1">Log Out</a>
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
         <xsl:attribute name="src">images/cdash.gif</xsl:attribute>
        </xsl:otherwise>
        </xsl:choose>
        </img>
      </a>
    </div>
    <div id="headername2">
      CDash
      <span id="subheadername">
        Projects
      </span>
    </div>
 </div>
</div>


<!-- Main table -->
<br/>

<xsl:if test="string-length(cdash/upgradewarning)>0">
  <p style="color:red"><b>The current database shema doesn't match the version of CDash you are running,
    upgrade your database structure in the <a href="upgrade.php">Administration/CDash maintenance panel of CDash</a></b></p>
</xsl:if>

<table border="0" cellpadding="4" cellspacing="0" width="100%" id="indexTable" class="tabb">
<thead>
<tr class="table-heading1">
  <td colspan="6" align="left" class="nob"><h3>Dashboards</h3></td>
</tr>

  <tr class="table-heading">
     <th align="center" id="sort_0"><b>Project</b></th>
     <td align="center"><b>Description</b></td>
     <th align="center" id="sort_1"><b>Submissions</b></th>
     <th align="center" id="sort_2"><b>Total Uploads</b></th>
     <th align="center" id="sort_3"><b>First build</b></th>
     <th align="center" id="sort_4" class="nob"><b>Last activity</b></th>
  </tr>
 </thead>
 <tbody>
   <xsl:for-each select="cdash/project">
   <tr>
   <xsl:if test="active=0">
   <xsl:attribute name="class">nonactive</xsl:attribute>
   </xsl:if>

   <td align="center" >
     <a>
     <xsl:attribute name="href">index.php?project=<xsl:value-of select="name_encoded"/></xsl:attribute>
     <xsl:value-of select="name"/>
     </a></td>
     <td align="center"><xsl:value-of select="description"/></td>
     <td align="center"><xsl:value-of select="nbuilds"/></td>
     <td align="center"><xsl:value-of select="uploadsize"/> GB</td>
     <td align="center">
         <span class="builddateelapsed">
         <xsl:attribute name="alt"><xsl:value-of select="firstbuild"/></xsl:attribute>
         <xsl:value-of select="firstbuild_elapsed"/>
         </span>
     </td>
    <td align="center" class="nob">
    <a class="builddateelapsed">
     <xsl:attribute name="alt"><xsl:value-of select="lastbuild"/></xsl:attribute>
      <xsl:attribute name="href">index.php?project=<xsl:value-of select="name_encoded"/>&amp;date=<xsl:value-of select="lastbuilddate"/></xsl:attribute>
      <xsl:value-of select="lastbuild_elapsed"/>
    </a>
    </td>
    </tr>
   </xsl:for-each>
</tbody>
</table>

<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td height="1" colspan="14" align="left" bgcolor="#888888"></td>
</tr>
<tr>
<td height="1" colspan="14" align="right">
<div id="showold"><a href="#" onclick="javascript:showoldproject()">Show all <xsl:value-of select="count(cdash/project)"/> projects</a></div>
<div id="hideold"><a href="#" onclick="javascript:hideoldproject()">Hide old projects</a></div>
</td>
</tr>
</table>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
