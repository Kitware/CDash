<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/>

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
         <xsl:comment><![CDATA[[if IE]>
          <link rel="stylesheet" href="css/tabs_ie.css" type="text/css" media="projection, screen" />
          <![endif]]]></xsl:comment>
         <xsl:call-template name="headscripts"/>
          <script type="text/javascript">
          function saveChanges()
            {
              $("#changesmade").show();
            }
         </script>
         <script type="text/javascript" src="js/OptionTransfer.js"></script>
         <script type="text/javascript" src="js/cdashUserLabels.js"></script>
         <script type="text/javascript" src="js/ui.tabs.js"></script>
       </head>
       <body bgcolor="#ffffff" onload="opt.init(document.forms[1])">

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
    <select onchange="location='subscribeProject.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
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
            <a href="#fragment-2"><span>Repository Credential</span></a></li>
          <li>
            <a href="#fragment-3"><span>Email Notifications</span></a></li>
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
            <td><input type="radio" onchange="saveChanges();" name="role" value="0" checked="checked">
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
            <td ><input type="radio" onchange="saveChanges();" name="role" value="2" checked="checked">
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
        <table width="800">
         <tr>
            <td></td>
            <td>Your repository credentials are used to match your repository id with cdash and send you alerts.<br/>
            To change your global credentials go to "My Profile".
            </td>
          </tr>
          <tr>
            <td></td>
            <td>Global Credentials:
             <xsl:for-each select="/cdash/global_credential">
               '<xsl:value-of select="."/>'
             </xsl:for-each>
            </td>
          </tr>
          <tr>
            <td></td>
            <td>Credential #1: <input onchange="saveChanges();" type="text" name="credentials[0]" size="30">
             <xsl:attribute name="value">
               <xsl:value-of select="cdash/credential_0"/>
             </xsl:attribute>
             </input>
             </td>
          </tr>
          <tr>
            <td></td>
            <td>Credential #2: <input onchange="saveChanges();" type="text" name="credentials[1]" size="30">
             <xsl:attribute name="value">
               <xsl:value-of select="cdash/credential_1"/>
             </xsl:attribute>
             </input>
             </td>
          </tr>
          <tr>
            <td></td>
            <td>Credential #3: <input onchange="saveChanges();" type="text" name="credentials[2]" size="30">
             <xsl:attribute name="value">
               <xsl:value-of select="cdash/credential_2"/>
             </xsl:attribute>
             </input>
             </td>
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
               <xsl:attribute name="href">createProject.php?projectid=<xsl:value-of select="/cdash/project/id"/>##tab5</xsl:attribute>Change the project settings.
               </a></xsl:when>
               <xsl:otherwise> Contact the project administrator.</xsl:otherwise>
             </xsl:choose>
            </font></td>
          </tr>
          </xsl:if>
          <xsl:if test="/cdash/edit=1">
           <tr>
            <td></td>
            <td><b>Email me:</b></td>
           </tr>
           <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="0">
             <xsl:if test="/cdash/emailtype=0">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> never (this is not recommended)
           </td>
          </tr>
            </xsl:if>
           <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="1">
             <xsl:if test="/cdash/emailtype=1 or /cdash/edit=0">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> when <b>my checkins</b> are causing problems in <b>any sections</b> of the dashboard
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="2">
             <xsl:if test="/cdash/emailtype=2">
             <xsl:attribute name="checked">
             </xsl:attribute>
             </xsl:if></input> when <b>any checkins</b> are causing problems in the <b>Nightly section</b> of the dashboard
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="3">
             <xsl:if test="/cdash/emailtype=3"><xsl:attribute name="checked"></xsl:attribute></xsl:if>
             </input> when <b>any checkins</b> are causing problems in <b>any sections</b> of the dashboard
           </td>
          </tr>
         <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailsuccess" value="1">
             <xsl:if test="/cdash/emailsuccess=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> when <b>my checkins</b> are fixing build errors, warnings or tests
           </td>
         </tr>
         <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailmissingsites" value="1">
             <xsl:if test="/cdash/emailmissingsites=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> when expected sites are not submitting
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
               <xsl:attribute name="href">createProject.php?projectid=<xsl:value-of select="/cdash/project/id"/>##tab5</xsl:attribute>Change the project settings.
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
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_dynamicanalysis" value="64">
             <xsl:if test="/cdash/emailcategory_dynamicanalysis=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Dynamic Analysis
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
               <xsl:attribute name="href">createProject.php?projectid=<xsl:value-of select="/cdash/project/id"/>##tab5</xsl:attribute>Change the project settings.
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
             <select name="movelabels[]" size="15" multiple="multiple" id="movelabels" ondblclick="rightTransfer()">
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
            <select name="emaillabels[]" size="15" multiple="multiple" id="emaillabels" ondblclick="leftTransfer()">
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
