<xsl:stylesheet
xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

<xsl:template name="builds">
    <xsl:param name="type"/>
    
        <div class="section">
    <xsl:attribute name="id">group<xsl:value-of select="id"/></xsl:attribute>
     
   <xsl:if test="count($type/build)=0">
              <h4><a href="#gotop">No <xsl:value-of select="name"/> Builds</a></h4>
   </xsl:if>
   
    <xsl:if test="count($type/build)>0">
          <h4><a href="#gotop"><xsl:value-of select="$type/name"/></a></h4>
   
   <ul>
            <li>
              <h5>
              <table width="95%" cellpadding="0" cellspacing="0">
              <tr class="sectionheader">
              <td width="15%">U</td>
              <td width="15%">C</td>
              <td width="14%">E</td>
              <td width="14%">W</td>
              <td width="14%">TP</td>
              <td width="14%">TF</td>
              <td width="14%">TNR</td>
              </tr>
              </table>
              </h5>
   
      <xsl:for-each select="$type/build">              
                       
           <a class="buildlink">
               <xsl:attribute name="href">buildsummary.php?buildid=<xsl:value-of select="buildid"/>&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:attribute>

             <table width="95%" height="32" cellpadding="0" cellspacing="0">
             <tr class="sectionbuildodd">
              <td width="100%" style="text-align: left;" colspan="7" >
              <xsl:value-of select="site"/>
              <xsl:if test="string-length(builddate)>0">-<xsl:value-of select="builddate"/></xsl:if><br/>
              <b><xsl:value-of select="buildname"/></b></td>
              </tr>
            <tr  class="sectionbuildeven" valign="middle">
              <td width="15%"><xsl:value-of select="update"/></td>
              <td width="15%">
               <xsl:attribute name="class">
               <xsl:choose>
                 <xsl:when test="configure > 0">
                   error
                   </xsl:when>
                  <xsl:when test="string-length(configure)=0">
                   tr-odd
                   </xsl:when>     
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
             </xsl:attribute>
              <xsl:value-of select="configure"/></td>
              <td width="14%">
               <xsl:attribute name="class">
                <xsl:choose>
                  <xsl:when test="compilation/error > 0">
                    error
                    </xsl:when>
                   <xsl:when test="string-length(compilation/error)=0">
                    tr-odd
                    </xsl:when>     
                  <xsl:otherwise>
                   normal
                   </xsl:otherwise>
                </xsl:choose>
              </xsl:attribute>
              <xsl:value-of select="compilation/error"/></td>
              <td width="14%">
               <xsl:attribute name="class">
               <xsl:choose>
                 <xsl:when test="compilation/warning > 0">
                   warning
                   </xsl:when>
                  <xsl:when test="string-length(compilation/warning)=0">
                   tr-odd
                   </xsl:when>   
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
             </xsl:attribute>
              <xsl:value-of select="compilation/warning"/></td>
              <td width="14%">
              <xsl:attribute name="class">
              <xsl:choose>
                <xsl:when test="test/fail > 0">
                  warning
                  </xsl:when>
                   <xsl:when test="string-length(test/fail)=0">
                  tr-odd
                  </xsl:when>       
                <xsl:otherwise>
                 normal
                 </xsl:otherwise>
              </xsl:choose>
            </xsl:attribute>
              <xsl:value-of select="test/pass"/>
              </td>
              <td width="14%">
               <xsl:attribute name="class">
              <xsl:choose>
                <xsl:when test="test/fail > 0">
                  warning
                  </xsl:when>
                <xsl:when test="string-length(test/fail)=0">
                  tr-odd
                  </xsl:when>  
                <xsl:otherwise>
                 normal
                 </xsl:otherwise>
              </xsl:choose>
            </xsl:attribute>
          <xsl:value-of select="test/fail"/>       
              </td>
              <td width="14%">
              <xsl:attribute name="class">
                <xsl:choose>
                  <xsl:when test="test/notrun > 0">
                    error
                    </xsl:when>
                  <xsl:when test="string-length(test/notrun)=0">
                    tr-odd
                    </xsl:when>    
                  <xsl:otherwise>
                   normal
                   </xsl:otherwise>
                </xsl:choose>
              </xsl:attribute>
              <xsl:value-of select="test/notrun"/>  
              </td>
              </tr>
         </table>
            </a>
  </xsl:for-each>
              </li>
            </ul>
    
  </xsl:if>
</div>
</xsl:template>
    
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
         
         </head><body orient="landscape">


         
    <h1 id="pageTitle">CDash</h1>
    <a href="http://cdash.org/iphone" class="home"></a>
    <a class="showPage button" href="#loginForm">Login</a>
    <a class="showPage title">CDash by Kitware Inc.</a>
    
     <ul id="projects" title="Project" selection="true" class="nobg">
        <li>   
        <div id="gotop"></div>      
          <h3><a>
          <xsl:attribute name="href">
            <xsl:value-of select="cdash/dashboard/home"/>
          </xsl:attribute>
          
          <xsl:value-of select="cdash/dashboard/projectname"/></a></h3>
          
                <div class="news-details">
                <div><xsl:value-of select="cdash/dashboard/datetime"/></div>
                <div>
                
                <table width="100%">
                <tr>
                <td style="text-align: left;font-size: 14;">
                <a>
                <xsl:attribute name="href">project.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&amp;date=<xsl:value-of select="cdash/dashboard/previousdate"/>
                </xsl:attribute><b>[Previous]</b></a>
                </td>
                 <td  style="text-align: right;font-size: 14;">
                 <a>
                <xsl:attribute name="href">project.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&amp;date=<xsl:value-of select="cdash/dashboard/nextdate"/>
                </xsl:attribute><b>[Next]</b></a>
                </td>
                </tr>
                </table>
                <hr/>
                <!--  Show the buildgroups -->
                <xsl:for-each select="cdash/buildgroup">
                <a><xsl:attribute name="href">#group<xsl:value-of select="id"/></xsl:attribute>[<xsl:value-of select="name"/>]</a><br/> 
                </xsl:for-each>
                 </div>
                </div>
       

<xsl:for-each select="cdash/buildgroup">
  <xsl:call-template name="builds">
  <xsl:with-param name="type" select="."/>
  </xsl:call-template>
</xsl:for-each>

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
