<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>

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
          <xsl:call-template name="headscripts"/>
       </head>
       <body>

<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="header_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="header"/>
</xsl:otherwise>
</xsl:choose>

<table border="0" width="100%">
<xsl:if test="cdash/banner">
  <tr bgcolor="#DDDDDD">
  <td align="center" width="100%" colspan="2">
  <b><xsl:value-of select="cdash/banner/text"/></b>
  </td>
  </tr>
  </xsl:if>
</table>

<!-- Main table -->
<br/>

<xsl:if test="string-length(cdash/upgradewarning)>0">
  <p style="color:red"><b>The current database schema doesn't match the version of CDash you are running,
    upgrade your database structure in the <a href="upgrade.php">Administration/CDash maintenance panel of CDash</a></b></p>
</xsl:if>

<table border="0" cellpadding="4" cellspacing="0" width="100%"  class="tabb" >
<!-- Display the project -->
<tr class="table-heading1">
  <td colspan="11" align="left" class="nob">
  <h3>Project</h3></td>
</tr>

  <tr class="table-heading">
     <td align="center" rowspan="2" width="20%"><b>Project</b></td>
     <td align="center" colspan="3" width="20%"><b>Configure</b></td>
     <td align="center" colspan="3" width="20%"><b>Build</b></td>
     <td align="center" colspan="3" width="20%"><b>Test</b></td>
     <td align="center" rowspan="2" width="20%" class="nob"><b>Last submission</b></td>
  </tr>
   <tr class="table-heading">
     <td align="center"><b>Error</b></td>
     <td align="center"><b>Warning</b></td>
     <td align="center"><b>Pass</b></td>
     <td align="center"><b>Error</b></td>
     <td align="center"><b>Warning</b></td>
     <td align="center"><b>Pass</b></td>
     <td align="center"><b>Not Run</b></td>
     <td align="center"><b>Fail</b></td>
     <td align="center"><b>Pass</b></td>
  </tr>
   <tr class="treven">
   <td align="center">
     <a>
     <xsl:attribute name="href">index.php?project=<xsl:value-of select="/cdash/dashboard/projectname_encoded"/>&amp;display=project<xsl:if test="string-length(/cdash/dashboard/date)>0">&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:if></xsl:attribute>
     <xsl:value-of select="/cdash/dashboard/projectname"/>
     </a>
     <a>
     <xsl:attribute name="href">index.php?project=<xsl:value-of select="/cdash/dashboard/projectname_encoded"/>&amp;display=project<xsl:if test="string-length(/cdash/dashboard/date)>0">&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:if>&amp;showfilters=1</xsl:attribute>
     <img border="0" src="images/filter.gif"/>
     </a>

     </td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/project/nconfigureerror>0">error</xsl:when>
          <xsl:otherwise>
          <xsl:choose>
          <xsl:when test="/cdash/project/nconfigureerror=0 and /cdash/project/nconfigurewarning=0 and /cdash/project/nconfigurepass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="/cdash/project/nconfigureerror!=0 or /cdash/project/nconfigurewarning!=0 or /cdash/project/nconfigurepass!=0">
    <xsl:value-of select="/cdash/project/nconfigureerror"/>
    </xsl:if>
    </td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/project/nconfigurewarning>0">warning</xsl:when>
          <xsl:otherwise>
          <xsl:choose>
          <xsl:when test="/cdash/project/nconfigureerror=0 and /cdash/project/nconfigurewarning=0 and /cdash/project/nconfigurepass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="/cdash/project/nconfigureerror!=0 or /cdash/project/nconfigurewarning!=0 or /cdash/project/nconfigurepass!=0">
    <xsl:value-of select="/cdash/project/nconfigurewarning"/>
    </xsl:if>
    </td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/project/nconfigureerror=0 and /cdash/project/nconfigurewarning=0 and /cdash/project/nconfigurepass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="/cdash/project/nconfigureerror!=0 or /cdash/project/nconfigurewarning!=0 or /cdash/project/nconfigurepass!=0">
    <xsl:value-of select="/cdash/project/nconfigurepass"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/project/nbuilderror>0">error</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="/cdash/project/nbuilderror=0 and /cdash/project/nbuildwarning=0 and /cdash/project/nbuildpass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="/cdash/project/nbuilderror!=0 or /cdash/project/nbuildwarning!=0 or /cdash/project/nbuildpass!=0">
    <xsl:value-of select="/cdash/project/nbuilderror"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/project/nbuildwarning>0">warning</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="/cdash/project/nbuilderror=0 and /cdash/project/nbuildwarning=0 and /cdash/project/nbuildpass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="/cdash/project/nbuilderror!=0 or /cdash/project/nbuildwarning!=0 or /cdash/project/nbuildpass!=0">
    <xsl:value-of select="/cdash/project/nbuildwarning"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/project/nbuildpass>0">normal</xsl:when>
          <xsl:otherwise></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="/cdash/project/nbuilderror!=0 or /cdash/project/nbuildwarning!=0 or /cdash/project/nbuildpass!=0">
    <xsl:value-of select="/cdash/project/nbuildpass"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/project/ntestnotrun>0">warning</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="string-length(/cdash/project/ntestnotrun)=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="/cdash/project/ntestfail!=0 or /cdash/project/ntestpass!=0 or /cdash/project/ntestnotrun!=0">
    <xsl:value-of select="/cdash/project/ntestnotrun"/>
    </xsl:if>
    </td>
  <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/project/ntestfail>0">error</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="string-length(/cdash/project/ntestfail)=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="/cdash/project/ntestfail!=0 or /cdash/project/ntestpass!=0 or /cdash/project/ntestnotrun!=0">
    <xsl:value-of select="/cdash/project/ntestfail"/>
    </xsl:if>
    </td>
  <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/project/ntestpass>0">normal</xsl:when>
          <xsl:otherwise></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="/cdash/project/ntestfail!=0 or /cdash/project/ntestpass!=0 or /cdash/project/ntestnotrun!=0">
    <xsl:value-of select="/cdash/project/ntestpass"/>
    </xsl:if>
    </td>
    <td align="center" class="nob"><xsl:value-of select="/cdash/project/lastsubmission"/></td>
    </tr>
</table>

<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb" id="subproject">
<!-- Display the subprojects -->
<thead>
<tr class="table-heading1">
  <td colspan="11" align="left" class="nob"><h3>SubProjects</h3></td>
</tr>
  <tr class="table-heading">
     <th align="center" rowspan="2" width="20%" id="sort_0"><b>Project</b></th>
     <td align="center" colspan="3" width="20%"><b>Configure</b></td>
     <td align="center" colspan="3" width="20%"><b>Build</b></td>
     <td align="center" colspan="3" width="20%"><b>Test</b></td>
     <th align="center" rowspan="2" width="20%" class="nob" id="sort_10"><b>Last submission</b></th>
  </tr>
   <tr class="table-heading">
     <th align="center" id="sort_1"><b>Error</b></th>
     <th align="center" id="sort_2"><b>Warning</b></th>
     <th align="center" id="sort_3"><b>Pass</b></th>
     <th align="center" id="sort_4"><b>Error</b></th>
     <th align="center" id="sort_5"><b>Warning</b></th>
     <th align="center" id="sort_6"><b>Pass</b></th>
     <th align="center" id="sort_7"><b>Not Run</b></th>
     <th align="center" id="sort_8"><b>Fail</b></th>
     <th align="center" id="sort_9"><b>Pass</b></th>
  </tr>
</thead>
  <tbody>
  <xsl:for-each select="cdash/subproject">
   <tr>
   <td align="center" >
     <a>
     <xsl:attribute name="href">index.php?project=<xsl:value-of select="/cdash/dashboard/projectname_encoded"/>&amp;subproject=<xsl:value-of select="name_encoded"/><xsl:if test="string-length(/cdash/dashboard/date)>0">&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:if></xsl:attribute>
     <xsl:value-of select="name"/>
     </a></td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nconfigureerror>0">error</xsl:when>
          <xsl:otherwise>
          <xsl:choose>
          <xsl:when test="nconfigureerror=0 and nconfigurewarning=0 and nconfigurepass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="nconfigureerror!=0 or nconfigurewarning!=0 or nconfigurepass!=0">
    <xsl:value-of select="nconfigureerror"/>
    </xsl:if>
    </td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nconfigurewarning>0">warning</xsl:when>
          <xsl:otherwise>
          <xsl:choose>
          <xsl:when test="nconfigureerror=0 and nconfigurewarning=0 and nconfigurepass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="nconfigureerror!=0 or nconfigurewarning!=0 or nconfigurepass!=0">
    <xsl:value-of select="nconfigurewarning"/>
    </xsl:if>
    </td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nconfigureerror=0 and nconfigurewarning=0 and nconfigurepass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="nconfigureerror!=0 or nconfigurewarning!=0 or nconfigurepass!=0">
    <xsl:value-of select="nconfigurepass"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nbuilderror>0">error</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="nbuilderror=0 and nbuildwarning=0 and nbuildpass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="nbuilderror!=0 or nbuildwarning!=0 or nbuildpass!=0">
    <xsl:value-of select="nbuilderror"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nbuildwarning>0">warning</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="nbuilderror=0 and nbuildwarning=0 and nbuildpass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="nbuilderror!=0 or nbuildwarning!=0 or nbuildpass!=0">
      <xsl:value-of select="nbuildwarning"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nbuilderror!=0 or nbuildwarning!=0 or nbuildpass!=0">normal</xsl:when>
          <xsl:otherwise></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="nbuilderror!=0 or nbuildwarning!=0 or nbuildpass!=0">
      <xsl:value-of select="nbuildpass"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="ntestnotrun>0">warning</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="(ntestfail!=0 or ntestpass!=0 or ntestnotrun!=0) and string-length(ntestnotrun)>0">normal</xsl:when>
          <xsl:otherwise></xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="ntestfail!=0 or ntestpass!=0 or ntestnotrun!=0">
    <xsl:value-of select="ntestnotrun"/>
    </xsl:if>
    </td>
  <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="ntestfail>0">error</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="(ntestfail!=0 or ntestpass!=0 or ntestnotrun!=0) and string-length(ntestfail)>0">normal</xsl:when>
          <xsl:otherwise></xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="ntestfail!=0 or ntestpass!=0 or ntestnotrun!=0">
    <xsl:value-of select="ntestfail"/>
    </xsl:if>
    </td>
  <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="(ntestfail!=0 or ntestpass!=0 or ntestnotrun!=0) and string-length(ntestpass)>0">normal</xsl:when>
          <xsl:otherwise></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="ntestfail!=0 or ntestpass!=0 or ntestnotrun!=0">
    <xsl:value-of select="ntestpass"/>
    </xsl:if>
    </td>

    <td align="center" class="nob"><xsl:value-of select="lastsubmission"/></td>
    </tr>
   </xsl:for-each>
</tbody>
</table>

<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td height="1" colspan="14" align="left" bgcolor="#888888"></td>
</tr>
</table>

<br/>
<!-- Add link to view the subproject dependencies -->
<a>
<xsl:attribute name="href">viewSubprojectDependencies.php?project=<xsl:value-of select="/cdash/dashboard/projectname_encoded"/><xsl:if test="string-length(/cdash/dashboard/date)>0">&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:if></xsl:attribute>
[View Subproject Dependencies]
</a>
<a>
<xsl:attribute name="href">viewSubprojectDependenciesGraph.php?project=<xsl:value-of select="/cdash/dashboard/projectname_encoded"/><xsl:if test="string-length(/cdash/dashboard/date)>0">&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:if></xsl:attribute>
[Graph]
</a>

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
       </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
