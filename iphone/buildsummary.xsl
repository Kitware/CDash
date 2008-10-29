<xsl:stylesheet
xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
 
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
         <meta name="viewport" content="width=320; initial-scale=1.0; maximum-scale=1.0; user-scalable=1;"/>
        <style type="text/css" media="screen">@import "iphone.css";</style>
         <script type="application/x-javascript" src="jquery-1.1.4.js"></script>
         <script type="application/x-javascript" src="jquery-iphone.js"></script>
         <script type="application/x-javascript" src="iphone.js"></script>
         </head>
         
         <body orient="landscape">

   <div id="Top"></div>
    <h1 id="pageTitle">CDash</h1>
    <a href="http://cdash.org/iphone" class="home"></a>
    <a class="showPage button" href="#loginForm">Login</a>
    <a class="showPage title">CDash by Kitware Inc.</a>
     
     <ul id="projects" title="Project" selection="true" class="nobg">
        <li>        
          <h3><a href="http://www.itk.org"><xsl:value-of select="cdash/dashboard/projectname"/></a></h3>
          
                <div class="news-details">
                <div><xsl:value-of select="cdash/dashboard/datetime"/></div>
                <div><a>
                <xsl:attribute name="href">project.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&amp;date=<xsl:value-of select="cdash/dashboard/nextdate"/>
                </xsl:attribute>[Back]</a>
                 </div>
                </div>


 <!-- Build log for a single submission -->
 <div id="buildinfo">
    <b>Site Name: </b><xsl:value-of select="cdash/build/site"/>
    <br/><b>Build Name: </b><xsl:value-of select="cdash/build/name"/>
    <br/><b>Time: </b><xsl:value-of select="cdash/build/time"/>
    <br/><b>Type: </b><xsl:value-of select="cdash/build/type"/>
    </div>
    
    <xsl:if test="cdash/build/lastsubmitbuild>0">
    <p/><b>Last submission: </b><a>
     <xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="cdash/build/lastsubmitbuild"/></xsl:attribute><xsl:value-of select="cdash/build/lastsubmitdate"/></a>  
     </xsl:if> 
    
    <div id="buildtable">
    <table cellspacing="0" cellpadding="0">
      <tr><td>
      <table>
      <tr class="table-heading">
        <th colspan="3">Current Build</th>
      </tr>
      <tr class="table-heading">
        <th>Stage</th><th>Errors</th><th>Warnings</th>
      </tr>
       <tr class="tr-odd">
        <td><a href="#Stage0"><b>Update</b></a></td>
        <td align="right">
        <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/update/nerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       
        <b><xsl:value-of select="cdash/update/nerrors"/></b></td>
        <td align="right">
              <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/update/nwarnings > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
        
        <b><xsl:value-of select="cdash/update/nwarnings"/></b></td>
        </tr>
        <tr class="tr-even">
        <td><a href="#Stage1"><b>Configure</b></a></td>

        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/configure/nerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute><b><xsl:value-of select="cdash/configure/nerrors"/></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/configure/nwarnings > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><xsl:value-of select="cdash/configure/nwarnings"/></b></td>
       </tr>
        <tr class="tr-odd">
        <td><a href="#Stage2"><b>Build</b></a></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/build/nerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute><b><xsl:value-of select="cdash/build/nerrors"/></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/build/nwarnings > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute><b><xsl:value-of select="cdash/build/nwarnings"/></b></td>
       </tr>
       <tr class="tr-even">
        <td><a href="#Stage3"><b>Test</b></a></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/test/nfailed > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute><b><xsl:value-of select="cdash/test/nfailed"/></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/test/nnotrun> 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute><b><xsl:value-of select="cdash/test/nnotrun"/></b></td>
       </tr>
      </table>
      </td>
      <td>
      <!-- Previous build -->
      <xsl:if test="cdash/previousbuild">
      <table class="dart">
      <tr class="table-heading">
        <th colspan="3"><a>
        <xsl:attribute name="href">buildsummary.php?buildid=<xsl:value-of select="cdash/previousbuild/buildid"/></xsl:attribute>
        Previous Build
        </a>
        </th>
      </tr>
      <tr class="table-heading">
        <th>Stage</th><th>Errors</th><th>Warnings</th>
      </tr>
       <tr class="tr-odd">
        <td><b>Update</b></td>
        <td align="right">
        <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nupdateerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       
        <b><xsl:value-of select="cdash/previousbuild/nupdateerrors"/></b></td>
        <td align="right">
              <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nupdatewarnings > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
        
        <b><xsl:value-of select="cdash/previousbuild/nupdatewarnings"/></b></td>
        </tr>
        <tr class="tr-even">
        <td><b>Configure</b></td>

        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nconfigurenerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute><b><xsl:value-of select="cdash/previousbuild/nconfigureerrors"/></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nconfigurewarnings > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><xsl:value-of select="cdash/previousbuild/nconfigurewarnings"/></b></td>
       </tr>
        <tr class="tr-odd">
        <td><b>Build</b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute><b><xsl:value-of select="cdash/previousbuild/nerrors"/></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nwarnings > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute><b><xsl:value-of select="cdash/previousbuild/nwarnings"/></b></td>
       </tr>
       <tr class="tr-even">
        <td><b>Test</b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/ntestfailed > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute><b><xsl:value-of select="cdash/previousbuild/ntestfailed"/></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/ntestnotrun> 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute><b><xsl:value-of select="cdash/previousbuild/ntestnotrun"/></b></td>
       </tr>
      </table>
      </xsl:if>
      </td>
      </tr>
      </table>
      </div>

<!-- Update -->
<div id="Stage0">
<div class="tracknav">
[<a href="#top">Top</a>]
[<a href="#Stage0">Update</a>]
[<a href="#Stage1">Configure</a>]
[<a href="#Stage2">Build</a>|<a href="#Stage2Warnings">W</a>]
[<a href="#Stage3">Test</a>]
</div>
<b>Update (<xsl:value-of select="cdash/update/nerrors"/> errors, <xsl:value-of select="cdash/update/nwarnings"/> warnings)</b>
<br/><b>Start Time: </b><xsl:value-of select="cdash/update/starttime"/> 
<br/><b>End Time: </b><xsl:value-of select="cdash/update/endtime"/>
<br/><b>Update Command: </b> <xsl:value-of select="cdash/update/command"/>    
<br/><b>Update Type: </b> <xsl:value-of select="cdash/update/type"/>   
<br/><b>Number of Updates: </b><xsl:value-of select="cdash/update/nupdates"/>
</div>

<!-- Configure -->
<div id="Stage1">
<div class="tracknav">
[<a href="#top">Top</a>]
[<a href="#Stage0">Update</a>]
[<a href="#Stage1">Configure</a>]
[<a href="#Stage2">Build</a>|<a href="#Stage2Warnings">W</a>]
[<a href="#Stage3">Test</a>]
</div>
<b>Configure (<xsl:value-of select="cdash/configure/nerrors"/> errors, <xsl:value-of select="cdash/configure/nwarnings"/> warnings)</b>

<br/><b>Start Time: </b><xsl:value-of select="cdash/configure/starttime"/> 
<br/><b>End Time: </b><xsl:value-of select="cdash/configure/endtime"/>
<br/><b>Configure Command: </b> <xsl:value-of select="cdash/configure/command"/>    
<br/><b>Configure Return Value: </b> <xsl:value-of select="cdash/configure/status"/> 
<br/><b>Configure Output: </b>
<br/><div class="CodeText"><xsl:value-of select="cdash/configure/output" disable-output-escaping="yes"/></div>
</div>

<!-- Build -->
<div id="Stage2">
<div class="tracknav">
[<a href="#top">Top</a>]
[<a href="#Stage0">Update</a>]
[<a href="#Stage1">Configure</a>]
[<a href="#Stage2">Build</a>|<a href="#Stage2Warnings">W</a>]
[<a href="#Stage3">Test</a>]
</div><b>Build (<xsl:value-of select="cdash/build/nerrors"/> errors, <xsl:value-of select="cdash/build/nwarnings"/> warnings)</b>
        <br/><b>Build command: </b><div class="CodeText"><xsl:value-of select="cdash/build/command" disable-output-escaping="yes"/></div>
        <br/><b>Start Time: </b><xsl:value-of select="cdash/build/starttime"/>
        <br/><b>End Time: </b><xsl:value-of select="cdash/build/endtime"/>
        <br/>
<!-- Show the errors -->
<xsl:for-each select="cdash/build/error">
<div class="BuildError">
<xsl:if test="sourceline>0">
<hr/>
<b>Build Log line <xsl:value-of select="logline"/></b>
  <br/>
  File: <b><xsl:value-of select="sourcefile"/></b>
  Line: <b><xsl:value-of select="sourceline"/></b><xsl:text>&#x20;</xsl:text>
</xsl:if>
<div class="CodeText"><xsl:value-of select="precontext" disable-output-escaping="yes"/>
<br/><xsl:value-of select="text" disable-output-escaping="yes"/>
<br/><xsl:value-of select="postcontext" disable-output-escaping="yes"/>
</div>
</div>
</xsl:for-each>
</div> <!-- Stage 2 -->

<div id="Stage2">      
<div class="tracknav">
[<a href="#top">Top</a>]
[<a href="#Stage0">Update</a>]
[<a href="#Stage1">Configure</a>]
[<a href="#Stage2">Build</a>|<a href="#Stage2Warnings">W</a>]
[<a href="#Stage3">Test</a>]</div>
<b>Build Warnings (<xsl:value-of select="cdash/build/nwarnings"/>)</b>

<xsl:for-each select="cdash/build/warning">
<div class="BuildError">
<xsl:if test="sourceline>0">
<hr/>
<b>Build Log line <xsl:value-of select="logline"/></b>
  <br/>
  File: <b><xsl:value-of select="sourcefile"/></b>
  Line: <b><xsl:value-of select="sourceline"/></b><xsl:text>&#x20;</xsl:text>
</xsl:if>
<div class="CodeText"><xsl:value-of select="precontext" disable-output-escaping="yes"/>
<xsl:value-of select="text" disable-output-escaping="yes"/>
<xsl:value-of select="postcontext" disable-output-escaping="yes"/>
</div>
</div>
</xsl:for-each>      
</div> <!-- Stage 2 -->

<!-- Test -->
<div id="Stage3">
<div class="tracknav">
[<a href="#top">Top</a>]
[<a href="#Stage0">Update</a>]
[<a href="#Stage1">Configure</a>]
[<a href="#Stage2">Build</a>|<a href="#Stage2Warnings">W</a>]
[<a href="#Stage3">Test</a>]
</div>
<b>Tests (<xsl:value-of select="cdash/test/npassed"/>  passed, <xsl:value-of select="cdash/test/nfailed"/> failed, <xsl:value-of select="cdash/test/nnotrun"/> not run)</b>
</div>
<!-- 
<a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>[View Tests Summary]</a>
-->
<br/>
<br/>


     </li>
     </ul>
    <form id="loginForm" class="dialog" method="post" action="/login">
        <fieldset>
            <h1>Login</h1>
            <label class="inside" id="username-label" for="username">Username...</label> 
            <input id="username" name="side-username" type="text"/>

            <label class="inside" id="password-label" for="password">Password...</label>
            <input id="password" name="side-password" type="password"/>
            
            <input class="submitButton" value="Login" type="submit"/>
            <input name="processlogin" value="1" type="hidden"/>
            <input name="returnpage" value="/iphone" type="hidden"/>
        </fieldset>
    </form>
    
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
