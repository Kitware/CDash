<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/> 
   <xsl:include href="headscripts.xsl"/>
  
   <!-- Local includes -->
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="local/headerback.xsl"/> 
   
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link> 
          <xsl:comment><![CDATA[[if IE]></xsl:comment>
        <link rel="stylesheet" href="tabs_ie.css" type="text/css" media="projection, screen" />
        <xsl:comment><![ endif]]></xsl:comment>
         <xsl:call-template name="headscripts"/>
          <script type="text/javascript">
          function saveChanges()
            {
              $("#changesmade").show();
            }
         </script>
         <script type="text/javascript" src="javascript/OptionTransfer.js"></script> 
         <script type="text/javascript" src="javascript/cdashUserLabels.js"></script>
         <script type="text/javascript" src="javascript/ui.tabs.js"></script>
       </head>
       <body bgcolor="#ffffff" onLoad="opt.init(document.forms[1])">

<xsl:choose>         
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="headerback_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="headerback"/>
</xsl:otherwise>
</xsl:choose>  

<br/>

<xsl:if test="string-length(cdash/warning)>0">
<xsl:value-of select="cdash/warning"/>
</xsl:if>

<table width="100%"  border="0">
<tr>
    <td width="10%"><div align="right"><strong>Project:</strong></div></td>
    <td width="90%" >
    <xsl:if test="count(cdash/availableproject)>0">
    <form name="form1" method="post">
    <xsl:attribute name="action">subscribeProject.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
    <select onchange="location = 'subscribeProject.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
        <option value="0">Choose project</option>
        <xsl:for-each select="cdash/availableproject">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>
      </form>
     </xsl:if>
    <xsl:if test="count(cdash/availableproject)=0">
      <xsl:value-of select="cdash/project/name"/>
    </xsl:if>  
    </td>
  </tr>
<tr>
<td colspan="2">
<form name="form1" enctype="multipart/form-data" method="post" action="">
  <div id="wizard">
      <ul>
          <li>                 
            <a href="#fragment-1"><span>Select your role in this project</span></a></li>                
          <li>
            <a href="#fragment-2"><span>CVS/SVN login</span></a></li>
          <li>
            <a href="#fragment-3"><span>Email Preference</span></a></li>                
          <li>
            <a href="#fragment-4"><span>Email Category</span></a></li>
          <li>
            <a href="#fragment-5"><span>Email Labels</span></a></li>
      </ul>
    <div id="fragment-1" class="tab_content" >
      <div class="tab_help"></div>
        <table width="800" >
          <tr>
            <td></td>
            <td><input type="radio" onchange="saveChanges();" name="role" value="0" checked="true">
            <xsl:if test="/cdash/role=0">
            <xsl:attribute name="checked"></xsl:attribute>
            </xsl:if>
            </input>
             Normal user <i>(you are working on or using this project)</i></td>
          </tr>
           <tr>
            <td></td>
            <td><input type="radio" onchange="saveChanges();" name="role" value="1">
             <xsl:if test="/cdash/role=1">
            <xsl:attribute name="checked"></xsl:attribute>
            </xsl:if>
            </input>
             Site maintainer <i>(you are responsible for machines that are submitting builds for this project)</i></td>
          </tr>
          <xsl:if test="/cdash/role>1">
           <tr>
            <td></td>
            <td ><b>Warning: if you change to a normal or maintainer role you won't be able to go back.</b> </td>
            </tr>
          <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="role" value="2" checked="true">
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
            <td ><input type="radio" onchange="saveChanges();" name="role" value="3">
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
        </table>
    </div>
    <div id="fragment-2" class="tab_content" >
      <div class="tab_help"></div>
        <table width="800" >
          <tr>
            <td></td>
            <td >Login: <input onchange="saveChanges();" type="text" name="cvslogin" size="30">
             <xsl:attribute name="value">
               <xsl:value-of select="cdash/cvslogin"/>
             </xsl:attribute>
             </input>
             <br/><br/><i>Your login is used to send you an email when the dashboard breaks.</i></td>
          </tr>
          <tr>
            <td></td>
            <td bgcolor="#FFFFFF"></td>
          </tr>
        </table>
    </div>
    <div id="fragment-3" class="tab_content" >
      <div class="tab_help"></div>
        <table width="800" >
         <xsl:if test="/cdash/project/emailbrokensubmission=0">
          <tr>
            <td></td> 
            <td><font color="#900000">*This project has not been configured to send emails.
             <xsl:choose>
               <xsl:when test="/cdash/role>1"><a>
               <xsl:attribute name="href">createProject.php?edit=1&#38;projectid=<xsl:value-of select="/cdash/project/id"/>#fragment-5</xsl:attribute>Change the project settings.
               </a></xsl:when>
               <xsl:otherwise> Contact the project administrator.</xsl:otherwise>
             </xsl:choose>
            </font></td>
          </tr>
          </xsl:if>
          <xsl:if test="/cdash/edit=1">
           <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="0">
             <xsl:if test="/cdash/emailtype=0">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> No email
           </td>
          </tr>
            </xsl:if>
           <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="1">
             <xsl:if test="/cdash/emailtype=1 or /cdash/edit=0">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Email me when <b>my checkins</b> are breaking the dashboard
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="2">
             <xsl:if test="/cdash/emailtype=2">
             <xsl:attribute name="checked">
             </xsl:attribute>
             </xsl:if>     </input> Email me when checkins are breaking <b>nightly</b> dashboard
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="3">
             <xsl:if test="/cdash/emailtype=3"><xsl:attribute name="checked"></xsl:attribute></xsl:if>
             </input> Email me when <b>any builds</b> are breaking the dashboard
           </td>
          </tr>
         <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailsuccess" value="1">
             <xsl:if test="/cdash/emailsuccess=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Email me when my checkins are fixing build errors, warnings or tests
           </td>
         </tr>
         <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailmissingsites" value="1">
             <xsl:if test="/cdash/emailmissingsites=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Email me when expected sites are not submitting
           </td>
          </tr>  
        </table>
    </div>
    <div id="fragment-4" class="tab_content" >
      <div class="tab_help"></div>
        <table width="800" >
        <xsl:if test="/cdash/project/emailbrokensubmission=0">
          <tr>
            <td></td> 
            <td><font color="#900000">*This project has not been configured to send emails.
             <xsl:choose>
               <xsl:when test="/cdash/role>1"><a>
               <xsl:attribute name="href">createProject.php?edit=1&#38;projectid=<xsl:value-of select="/cdash/project/id"/>#fragment-5</xsl:attribute>Change the project settings.
               </a></xsl:when>
               <xsl:otherwise> Contact the project administrator.</xsl:otherwise>
             </xsl:choose>
            </font></td>
          </tr>
          </xsl:if>
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_update" value="2">
             <xsl:if test="/cdash/emailcategory_update=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Update
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_configure" value="4">
             <xsl:if test="/cdash/emailcategory_configure=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Configure
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_warning" value="8">
             <xsl:if test="/cdash/emailcategory_warning=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Warning
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_error" value="16">
             <xsl:if test="/cdash/emailcategory_error=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Error
           </td>
          </tr>  
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_test" value="32">
             <xsl:if test="/cdash/emailcategory_test=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Test
           </td>
          </tr>  
        </table>
    </div>
    <div id="fragment-5" class="tab_content">
      <div class="tab_help"></div>
        <table width="800">
        <xsl:if test="/cdash/project/emailbrokensubmission=0">
          <tr>
            <td colspan="2"><font color="#900000">*This project has not been configured to send emails.
             <xsl:choose>
               <xsl:when test="/cdash/role>1"><a>
               <xsl:attribute name="href">createProject.php?edit=1&#38;projectid=<xsl:value-of select="/cdash/project/id"/>#fragment-5</xsl:attribute>Change the project settings.
               </a></xsl:when>
               <xsl:otherwise> Contact the project administrator.</xsl:otherwise>
             </xsl:choose>
            </font></td>
          </tr>
          </xsl:if>
          <tr>
          <td colspan="2">Select the labels you want to subscribe to. You will receive only emails corresponding to these labels.</td>
          </tr>
          <tr>
            <td align="right">
             Available Labels (last 7 days)<br/>
             <select name="movelabels[]" size="15" multiple="true" id="movelabels" onDblClick="rightTransfer()">
                <xsl:for-each select="/cdash/project/label">
                <option>
                  <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                  <xsl:value-of select="text"/>
                </option>
                </xsl:for-each>
             </select>
            </td>
            <td align="center">
            <input name="addlabel" onclick="rightTransfer()" type="button" value="&gt;&gt;" /><br/><br/>
            <input name="removelabel" onclick="leftTransfer()" type="button" value="&lt;&lt;" />
            </td>
            <td align="left">
             Email Labels <br/>
            <select name="emaillabels[]" size="15" multiple="true" id="emaillabels" onDblClick="leftTransfer()">
                <xsl:for-each select="/cdash/project/labelemail">
                <option>
                  <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                  <xsl:value-of select="text"/>
                </option>
                </xsl:for-each>
             </select>
           </td>
          </tr>
        </table>
    </div>
 </div>
 
 <xsl:if test="/cdash/edit=1">
  <br/>
  <div style="width:900px;margin-left:auto;margin-right:auto;text-align:right;">
  <table width="100%" border="0">
  <tr>
    <td style="text-align:left;" ><input type="submit" onclick="return confirm('Are you sure you want to unsubscribe?')" name="unsubscribe" value="Unsubscribe"/></td>
    <td><span id="changesmade" style="color:red;display:none;">*Changes need to be updated </span>
    <input type="submit" onclick="SubmitForm()" name="updatesubscription" value="Update Subscription"/></td>
   </tr>
  </table>
  </div>
  </xsl:if>
  <xsl:if test="/cdash/edit=0">
   <div style="width:900px;margin-left:auto;margin-right:auto;text-align:right;"><br/>
  <input type="submit" name="subscribe" value="Subscribe"/>
  </div>
</xsl:if> 
    
</form>
</td>
</tr>
</table>

<br/>
<!-- FOOTER -->
<br/>

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
