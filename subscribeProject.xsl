<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/> 

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>

    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link> 
       </head>
       <body bgcolor="#ffffff">
  <xsl:call-template name="headerback"/>

<br/>

<xsl:if test="string-length(cdash/warning)>0">
<xsl:value-of select="cdash/warning"/>
</xsl:if>

<form name="form1" enctype="multipart/form-data" method="post" action="">
<table width="100%"  border="0">
  <tr>
    <td></td>
    <td></td>
  </tr>
  <tr>
    <td width="98"></td>
    <td bgcolor="#CCCCCC"><strong>Select your role in this project</strong></td>
  </tr>
   <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><input type="radio" name="role" value="0" checked="true">
    <xsl:if test="/cdash/role=0">
    <xsl:attribute name="checked"></xsl:attribute>
    </xsl:if>
    </input>
     Normal user <i>(you are working on or using this toolkit)</i></td>
  </tr>
   <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><input type="radio" name="role" value="1">
     <xsl:if test="/cdash/role=1">
    <xsl:attribute name="checked"></xsl:attribute>
    </xsl:if>
    </input>
     Dashboard maintainer <i>(you are responsible of machines that are submitting builds for this project)</i></td>
  </tr>
  <xsl:if test="/cdash/role>1">
   <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><b>Warning: if you change to a normal or maintainer role you won't be able to go back.</b> </td>
    </tr>
  <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><input type="radio" name="role" value="2" checked="true">
    <xsl:if test="/cdash/role=2">
    <xsl:attribute name="checked"></xsl:attribute>
    </xsl:if>
    </input>
     Project Administrator <i>(You are administering the project)</i></td>
  </tr>
  </xsl:if>
  <xsl:if test="/cdash/role>2">
   <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><input type="radio" name="role" value="3">
     <xsl:if test="/cdash/role=3">
    <xsl:attribute name="checked"></xsl:attribute>
    </xsl:if>
    </input>
      Project Super Administrator<i>(You have full control of this project)</i></td>
  </tr>
  </xsl:if>
   <tr>
    <td></td>
    <td bgcolor="#FFFFFF"></td>
  </tr> 
  <tr>
    <td width="98"></td>
    <td bgcolor="#CCCCCC"><strong>CVS/SVN login</strong></td>
  </tr>
   <tr>
    <td></td>
    <td bgcolor="#EEEEEE">Login: <input type="text" name="cvslogin" size="30">
     <xsl:attribute name="value">
       <xsl:value-of select="cdash/cvslogin"/>
     </xsl:attribute>
     </input>
     <i>(your login is used to send you an email when the dashboard breaks)</i></td>
  </tr>
  <tr>
    <td></td>
    <td bgcolor="#FFFFFF"></td>
  </tr>
  <tr>
    <td width="98"></td>
    <td bgcolor="#CCCCCC"><strong>Email Preference</strong></td>
  </tr>
  <xsl:if test="/cdash/edit=1">
   <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><input type="radio" name="emailtype" value="0">
     <xsl:if test="/cdash/emailtype=0">
     <xsl:attribute name="checked"></xsl:attribute>
     </xsl:if>
     </input> No email
   </td>
  </tr>
    </xsl:if>
   <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><input type="radio" name="emailtype" value="1">
     <xsl:if test="/cdash/emailtype=1 or /cdash/edit=0">
     <xsl:attribute name="checked"></xsl:attribute>
     </xsl:if>
     </input> Email me when <b>my checkins</b> are breaking the dashboard
   </td>
  </tr>
  <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><input type="radio" name="emailtype" value="2">
     <xsl:if test="/cdash/emailtype=2">
     <xsl:attribute name="checked">
     </xsl:attribute>
     </xsl:if>     </input> Email me when checkins are breaking <b>nightly</b> dashboard
   </td>
  </tr>
  <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><input type="radio" name="emailtype" value="3">
     <xsl:if test="/cdash/emailtype=3"><xsl:attribute name="checked"></xsl:attribute></xsl:if>
     </input> Email me when <b>any builds</b> are breaking the dashboard
   </td>
  </tr>
  
  <tr>
    <td></td>
    <td bgcolor="#FFFFFF">
    <xsl:if test="/cdash/edit=1">
      <input type="submit" name="updatesubscription" value="Update Subscription"/>
     <input type="submit" name="unsubscribe" value="Unsubscribe"/>
      </xsl:if>
      <xsl:if test="/cdash/edit=0">
      <input type="submit" name="subscribe" value="Subscribe"/>
    </xsl:if>
    </td>
  </tr> 
</table>
</form>
<br/>

<br/>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
