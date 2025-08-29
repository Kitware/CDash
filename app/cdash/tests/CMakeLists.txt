# We define these here so they can be referenced in functions below.
cdash_install(install_1)
cdash_install(install_2)

# function to add a new PHP based coverage test to CDash
function(add_php_test TestName)
  add_test(
    NAME ${TestName}
    COMMAND ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_${TestName}.php
  )
  set_property(TEST ${TestName} PROPERTY FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*")
  set_property(TEST ${TestName} PROPERTY PASS_REGULAR_EXPRESSION ".*OK.*")
endfunction()

function(add_legacy_unit_test TestName)
  add_test(
    NAME ${TestName}
    COMMAND
      ${PHP_EXE}
      ${phpunit_extra_arg}
      ${PHPUNIT}
      -c ${testing_dir}/phpunit.xml
      --bootstrap ${testing_dir}/bootstrap.php
      ${testing_dir}/case/${TestName}Test.php
  )
endfunction()

function(add_laravel_test TestName)
  add_test(
    NAME ${TestName}
    COMMAND
      ${PHP_EXE}
      ${phpunit_extra_arg}
      ${PHPUNIT}
      -c ${CDash_SOURCE_DIR}/phpunit.xml
      ${laravel_testing_dir}/${TestName}.php
  )
endfunction()

# Tests with no dependencies whatsoever
function(add_unit_test TestName)
  add_laravel_test(${TestName})
endfunction()

# Tests with no dependencies on other tests which require the database to be available
function(add_feature_test TestName)
  add_laravel_test(${TestName})
  set_property(TEST ${TestName} APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)
  set_property(TEST install_2 APPEND PROPERTY DEPENDS ${TestName})
endfunction()

function(add_browser_test TestName)
  add_feature_test(${TestName})
  set_property(TEST ${TestName} APPEND PROPERTY RESOURCE_LOCK
    APP_URL # All browser tests need exclusive access to the APP_URL configuration setting
  )
  set_property(TEST ${TestName} APPEND PROPERTY PROCESSORS 2)
endfunction()

# phpunit tests
set(phpunit_extra_arg "")
if (CDASH_CONFIGURE_HTACCESS_FILE)
    set(phpunit_extra_arg "-d auto_prepend_file=${CMAKE_BINARY_DIR}/prepend_coverage.php")
endif()

###################################################################################################
# Tests from this point to the next cdash_install() are expected to not need database or other
# system resources.

add_unit_test(/Unit/app/ControllerNameTest)

add_unit_test(/Unit/app/FillableAttributesTest)

add_unit_test(/Unit/app/Validators/PasswordTest)

add_unit_test(/Unit/app/Middleware/InternalTest)

# TODO: Is this test useful now?
add_legacy_unit_test(/PHPUnitTest)

add_legacy_unit_test(/CDash/ServiceContainer)

add_legacy_unit_test(/CDash/LinkifyCompilerOutput)

add_unit_test(/Unit/app/Console/Command/ValidateXmlCommandTest)

add_php_test(seconds_from_interval)

add_php_test(extracttar)

add_php_test(passwordcomplexity)

add_legacy_unit_test(/CDash/Model/BuildError)

add_legacy_unit_test(/CDash/Model/Build)

add_legacy_unit_test(/CDash/Model/BuildErrorFilter)

add_legacy_unit_test(/CDash/Model/BuildFailure)

add_legacy_unit_test(/CDash/Model/Repository)

###################################################################################################

add_laravel_test(/Feature/CDashTest)
set_property(TEST /Feature/CDashTest APPEND PROPERTY DEPENDS install_1)

add_legacy_unit_test(/CDash/Model/BuildRelationship)
set_property(TEST /CDash/Model/BuildRelationship APPEND PROPERTY DEPENDS /Feature/CDashTest)

add_legacy_unit_test(/CDash/MultipleSubprojectsEmail)
set_property(TEST /CDash/MultipleSubprojectsEmail APPEND PROPERTY DEPENDS /CDash/Model/BuildRelationship)

add_legacy_unit_test(/CDash/NightlyTime)
set_property(TEST /CDash/NightlyTime APPEND PROPERTY DEPENDS /CDash/MultipleSubprojectsEmail)

add_legacy_unit_test(/CDash/TestUseCase)
set_property(TEST /CDash/TestUseCase APPEND PROPERTY DEPENDS /CDash/NightlyTime)

add_legacy_unit_test(/CDash/BuildUseCase)
set_property(TEST /CDash/BuildUseCase APPEND PROPERTY DEPENDS /CDash/TestUseCase)

add_legacy_unit_test(/CDash/ConfigUseCase)
set_property(TEST /CDash/ConfigUseCase APPEND PROPERTY DEPENDS /CDash/BuildUseCase)

add_legacy_unit_test(/CDash/UpdateUseCase)
set_property(TEST /CDash/UpdateUseCase APPEND PROPERTY DEPENDS /CDash/ConfigUseCase)

add_legacy_unit_test(/CDash/XmlHandler/BuildHandler)
set_property(TEST /CDash/XmlHandler/BuildHandler APPEND PROPERTY DEPENDS /CDash/UpdateUseCase)

add_legacy_unit_test(/CDash/XmlHandler/ConfigureHandler)
set_property(TEST /CDash/XmlHandler/ConfigureHandler APPEND PROPERTY DEPENDS /CDash/XmlHandler/BuildHandler)

add_legacy_unit_test(/CDash/XmlHandler/DynamicAnalysisHandler)
set_property(TEST /CDash/XmlHandler/DynamicAnalysisHandler APPEND PROPERTY DEPENDS /CDash/XmlHandler/ConfigureHandler)

add_legacy_unit_test(/CDash/XmlHandler/TestingHandler)
set_property(TEST /CDash/XmlHandler/TestingHandler APPEND PROPERTY DEPENDS /CDash/XmlHandler/DynamicAnalysisHandler)

add_legacy_unit_test(/CDash/XmlHandler/UpdateHandler)
set_property(TEST /CDash/XmlHandler/UpdateHandler APPEND PROPERTY DEPENDS /CDash/XmlHandler/TestingHandler)

###################################################################################################
# Tests from this point to the next cdash_install() are expected to be completely independent.
# Tests may wipe the database, provided that they use RUN_SERIAL. All tests in this section should
# depend upon the last test prior to this section.

add_feature_test(/Feature/LoginAndRegistration)

add_feature_test(/Feature/ProjectPermissions)

add_feature_test(/Feature/SlowPageTest)

add_feature_test(/Feature/GitHubWebhook)

add_feature_test(/Feature/GlobalBannerTest)

add_legacy_unit_test(/CDash/Lib/Repository/GitHub)
set_property(TEST /CDash/Lib/Repository/GitHub APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)
set_property(TEST /CDash/Lib/Repository/GitHub APPEND PROPERTY DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>")

add_legacy_unit_test(/CDash/Messaging/Subscription/CommitAuthorSubscriptionBuilder)
set_property(TEST /CDash/Messaging/Subscription/CommitAuthorSubscriptionBuilder APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Subscription/UserSubscriptionBuilder)
set_property(TEST /CDash/Messaging/Subscription/UserSubscriptionBuilder APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/UpdateErrorTopic)
set_property(TEST /CDash/Messaging/Topic/UpdateErrorTopic APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Service/RepositoryService)
set_property(TEST /CDash/Service/RepositoryService APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

# Set this to RUN_SERIAL as we are changing the config and don't want to clobber other tests.
# Only requires exclusive .env access.
add_feature_test(/Feature/SubmissionValidation)
set_property(TEST /Feature/SubmissionValidation APPEND PROPERTY RUN_SERIAL TRUE)

add_feature_test(/Feature/ProjectInvitationAcceptanceTest)

add_feature_test(/Feature/GraphQL/FilterTest)
set_property(TEST /Feature/GraphQL/FilterTest APPEND PROPERTY RUN_SERIAL TRUE)

add_feature_test(/Feature/GraphQL/QueryTypeTest)

add_feature_test(/Feature/GraphQL/UserTypeTest)

add_feature_test(/Feature/GraphQL/ProjectTypeTest)
set_property(TEST /Feature/GraphQL/ProjectTypeTest APPEND PROPERTY RUN_SERIAL TRUE)

add_feature_test(/Feature/GraphQL/SiteTypeTest)
set_property(TEST /Feature/GraphQL/SiteTypeTest APPEND PROPERTY RUN_SERIAL TRUE)

add_feature_test(/Feature/GraphQL/BuildTypeTest)

add_feature_test(/Feature/GraphQL/TestTypeTest)

add_feature_test(/Feature/GraphQL/TestMeasurementTypeTest)

add_feature_test(/Feature/GraphQL/NoteTypeTest)

add_feature_test(/Feature/GraphQL/CoverageTypeTest)

add_feature_test(/Feature/GraphQL/ProjectInvitationTypeTest)

add_feature_test(/Feature/GraphQL/LabelTypeTest)

add_feature_test(/Feature/GraphQL/TargetTypeTest)

add_feature_test(/Feature/GraphQL/BuildCommandTypeTest)

add_feature_test(/Feature/GraphQL/BuildCommandOutputTypeTest)

add_feature_test(/Feature/Submission/Instrumentation/BuildInstrumentationTest)
add_feature_test(/Feature/Submission/Tests/TestXMLTest)

add_feature_test(/Feature/RouteAccessTest)

add_feature_test(/Feature/Monitor)
set_property(TEST /Feature/Monitor APPEND PROPERTY RUN_SERIAL TRUE)

add_feature_test(/Feature/PasswordRotation)

add_browser_test(/Browser/Pages/SitesIdPageTest)

# Technically this test doesn't have to run serially.  It just needs to have exclusive access to the .env
add_feature_test(/Feature/LdapIntegration)
set_property(TEST /Feature/LdapIntegration APPEND PROPERTY RUN_SERIAL TRUE)

add_browser_test(/Browser/Pages/ProjectSitesPageTest)

add_browser_test(/Browser/Pages/ProjectMembersPageTest)

add_browser_test(/Browser/Pages/ProjectPageTest)

add_feature_test(/Feature/PurgeUnusedProjectsCommand)
set_property(TEST /Feature/PurgeUnusedProjectsCommand APPEND PROPERTY RUN_SERIAL TRUE)

# This could theoretically conflict with another test removing jobs from the submission tables in the future
add_feature_test(/Feature/Jobs/PruneJobsTest)

add_feature_test(/Feature/Jobs/PruneAuthTokensTest)
set_property(TEST /Feature/Jobs/PruneAuthTokensTest APPEND PROPERTY RUN_SERIAL TRUE)

add_feature_test(/Feature/UserCommand)

add_legacy_unit_test(/CDash/Database)
set_property(TEST /CDash/Database APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/AuthoredTopic)
set_property(TEST /CDash/Messaging/Topic/AuthoredTopic APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/BuildErrorTopic)
set_property(TEST /CDash/Messaging/Topic/BuildErrorTopic APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/ConfigureTopic)
set_property(TEST /CDash/Messaging/Topic/ConfigureTopic APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/DynamicAnalysisTopic)
set_property(TEST /CDash/Messaging/Topic/DynamicAnalysisTopic APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/EmailSentTopic)
set_property(TEST /CDash/Messaging/Topic/EmailSentTopic APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/FixedTopic)
set_property(TEST /CDash/Messaging/Topic/FixedTopic APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/MissingTestTopic)
set_property(TEST /CDash/Messaging/Topic/MissingTestTopic APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/TestFailureTopic)
set_property(TEST /CDash/Messaging/Topic/TestFailureTopic APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/TopicDecorator)
set_property(TEST /CDash/Messaging/Topic/TopicDecorator APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_feature_test(/Feature/RemoteWorkers)
set_property(TEST /Feature/RemoteWorkers APPEND PROPERTY
    DISABLED "$<NOT:$<STREQUAL:${CDASH_STORAGE_TYPE},local>>"
)

add_feature_test(/Feature/Jobs/NotifyExpiringAuthTokensTest)
set_property(TEST /Feature/Jobs/NotifyExpiringAuthTokensTest APPEND PROPERTY RUN_SERIAL TRUE)

add_feature_test(/Feature/Jobs/PruneUploadsTest)

add_feature_test(/Feature/Mail/AuthTokenExpiringTest)

add_feature_test(/Feature/Mail/AuthTokenExpiredTest)

add_feature_test(/Feature/GraphQL/Mutations/UpdateSiteDescriptionTest)

add_feature_test(/Feature/GraphQL/Mutations/InviteToProjectTest)

add_feature_test(/Feature/GraphQL/Mutations/RevokeProjectInvitationTest)

add_feature_test(/Feature/GraphQL/Mutations/ChangeProjectRoleTest)

add_feature_test(/Feature/GraphQL/Mutations/RemoveProjectUserTest)

add_feature_test(/Feature/Traits/UpdatesSiteInformationTest)

add_feature_test(/Feature/GraphQL/Mutations/ChangeGlobalRoleTest)

add_feature_test(/Feature/GraphQL/Mutations/JoinProjectTest)

# Needs RUN_SERIAL to verify that no unexpected invitations are created
add_feature_test(/Feature/GraphQL/Mutations/CreateGlobalInvitationTest)
set_property(TEST /Feature/GraphQL/Mutations/CreateGlobalInvitationTest APPEND PROPERTY RUN_SERIAL TRUE)

add_feature_test(/Feature/GraphQL/Mutations/RevokeGlobalInvitationTest)

# Needs RUN_SERIAL to verify that only the expected users are created
add_feature_test(/Feature/GlobalInvitationAcceptanceTest)
set_property(TEST /Feature/GlobalInvitationAcceptanceTest APPEND PROPERTY RUN_SERIAL TRUE)

# Requires exclusive access to the global_invitations table
add_feature_test(/Feature/GraphQL/GlobalInvitationTypeTest)
set_property(TEST /Feature/GraphQL/GlobalInvitationTypeTest APPEND PROPERTY RUN_SERIAL TRUE)

add_feature_test(/Feature/GraphQL/Mutations/RemoveUserTest)

add_browser_test(/Browser/Pages/UsersPageTest)

add_browser_test(/Browser/Pages/BuildFilesPageTest)

add_browser_test(/Browser/Pages/BuildNotesPageTest)

add_browser_test(/Browser/Pages/BuildTargetsPageTest)

add_php_test(image)
set_property(TEST image APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(changeid)
set_property(TEST changeid APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(consistenttestingday)
set_property(TEST consistenttestingday APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(numericupdate)
set_property(TEST numericupdate APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(attachedfiles)
set_property(TEST attachedfiles APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(subprojectorder)
set_property(TEST subprojectorder APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(dynamicanalysislogs)
set_property(TEST dynamicanalysislogs APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(dynamicanalysisdefectlongtype)
set_property(TEST dynamicanalysisdefectlongtype APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(configureappend)
set_property(TEST configureappend APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(filterbuilderrors)
set_property(TEST filterbuilderrors APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(buildproperties)
set_property(TEST buildproperties APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(issuecreation)
set_property(TEST issuecreation APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(junithandler)
set_property(TEST junithandler APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(configurewarnings)
set_property(TEST configurewarnings APPEND PROPERTY DEPENDS /CDash/XmlHandler/UpdateHandler)

add_feature_test(/Feature/AutoRemoveBuildsCommand)

add_feature_test(/Feature/Services/ProjectServiceTest)

###################################################################################################

set_property(TEST install_2 APPEND PROPERTY DEPENDS
  /CDash/Lib/Repository/GitHub
  /CDash/Messaging/Subscription/CommitAuthorSubscriptionBuilder
  /CDash/Messaging/Subscription/UserSubscriptionBuilder
  /CDash/Messaging/Topic/UpdateErrorTopic
  /CDash/Service/RepositoryService
  /CDash/Database
  /CDash/Messaging/Topic/AuthoredTopic
  /CDash/Messaging/Topic/BuildErrorTopic
  /CDash/Messaging/Topic/ConfigureTopic
  /CDash/Messaging/Topic/DynamicAnalysisTopic
  /CDash/Messaging/Topic/EmailSentTopic
  /CDash/Messaging/Topic/FixedTopic
  /CDash/Messaging/Topic/MissingTestTopic
  /CDash/Messaging/Topic/TestFailureTopic
  /CDash/Messaging/Topic/TopicDecorator
  cypress/e2e/user-profile
  image
  changeid
  consistenttestingday
  numericupdate
  attachedfiles
  subprojectorder
  dynamicanalysislogs
  dynamicanalysisdefectlongtype
  configureappend
  filterbuilderrors
  buildproperties
  issuecreation
  junithandler
  configurewarnings
)


add_php_test(compressedtest)
set_property(TEST compressedtest APPEND PROPERTY DEPENDS install_2)

add_php_test(createpublicdashboard)
set_property(TEST createpublicdashboard APPEND PROPERTY DEPENDS compressedtest)

add_php_test(email)
set_property(TEST email APPEND PROPERTY DEPENDS createpublicdashboard)

add_php_test(projectwebpage)
set_property(TEST projectwebpage APPEND PROPERTY DEPENDS email)

add_php_test(subproject)
set_property(TEST subproject APPEND PROPERTY DEPENDS projectwebpage)

add_php_test(actualtrilinossubmission)
set_property(TEST actualtrilinossubmission APPEND PROPERTY TIMEOUT 1800) # Slow tests that need more time in CI.
set_property(TEST actualtrilinossubmission APPEND PROPERTY DEPENDS subproject)

add_php_test(summaryemail)
set_property(TEST summaryemail APPEND PROPERTY DEPENDS actualtrilinossubmission)

add_php_test(aggregatecoverage)
set_property(TEST aggregatecoverage APPEND PROPERTY DEPENDS summaryemail)

add_php_test(buildconfigure)
set_property(TEST buildconfigure APPEND PROPERTY DEPENDS aggregatecoverage)

add_php_test(buildgrouprule)
set_property(TEST buildgrouprule APPEND PROPERTY DEPENDS buildconfigure)

add_php_test(buildoverview)
set_property(TEST buildoverview APPEND PROPERTY DEPENDS buildgrouprule)

add_php_test(committerinfo)
set_property(TEST committerinfo APPEND PROPERTY DEPENDS buildoverview)

add_php_test(displayimage)
set_property(TEST displayimage APPEND PROPERTY DEPENDS committerinfo)

add_php_test(projectindb)
set_property(TEST projectindb APPEND PROPERTY DEPENDS displayimage)

add_php_test(projectmodel)
set_property(TEST projectmodel APPEND PROPERTY DEPENDS projectindb)

add_php_test(querytests)
set_property(TEST querytests APPEND PROPERTY DEPENDS projectmodel)

add_php_test(sitestatistics)
set_property(TEST sitestatistics APPEND PROPERTY DEPENDS querytests)

add_php_test(testoverview)
set_property(TEST testoverview APPEND PROPERTY DEPENDS sitestatistics)

add_php_test(viewconfigure)
set_property(TEST viewconfigure APPEND PROPERTY DEPENDS testoverview)

add_php_test(viewdynamicanalysis)
set_property(TEST viewdynamicanalysis APPEND PROPERTY DEPENDS viewconfigure)

add_php_test(viewdynamicanalysisfile)
set_property(TEST viewdynamicanalysisfile APPEND PROPERTY DEPENDS viewdynamicanalysis)

add_php_test(buildmodel)
set_property(TEST buildmodel APPEND PROPERTY DEPENDS viewdynamicanalysisfile)

add_php_test(projectxmlsequence)
set_property(TEST projectxmlsequence APPEND PROPERTY DEPENDS buildmodel)

add_php_test(uploadfile)
set_property(TEST uploadfile APPEND PROPERTY DEPENDS projectxmlsequence)

add_php_test(actualbranchcoverage)
set_property(TEST actualbranchcoverage APPEND PROPERTY DEPENDS uploadfile)

add_php_test(unparsedsubmissionshonorbuildid)
set_property(TEST unparsedsubmissionshonorbuildid APPEND PROPERTY DEPENDS actualbranchcoverage)

add_php_test(multicoverage)
set_property(TEST multicoverage APPEND PROPERTY DEPENDS unparsedsubmissionshonorbuildid)

add_php_test(javajsoncoverage)
set_property(TEST javajsoncoverage APPEND PROPERTY DEPENDS multicoverage)

add_php_test(jscovercoverage)
set_property(TEST jscovercoverage APPEND PROPERTY DEPENDS javajsoncoverage)

add_php_test(opencovercoverage)
set_property(TEST opencovercoverage APPEND PROPERTY DEPENDS jscovercoverage)

add_php_test(buildfailuredetails)
set_property(TEST buildfailuredetails APPEND PROPERTY DEPENDS opencovercoverage)

add_php_test(builddetails)
set_property(TEST builddetails APPEND PROPERTY DEPENDS buildfailuredetails)

add_php_test(updateappend)
set_property(TEST updateappend APPEND PROPERTY DEPENDS builddetails)

add_php_test(notesapi)
set_property(TEST notesapi APPEND PROPERTY DEPENDS updateappend)

add_php_test(usernotes)
set_property(TEST usernotes APPEND PROPERTY DEPENDS notesapi)

add_php_test(hidecolumns)
set_property(TEST hidecolumns APPEND PROPERTY DEPENDS usernotes)

add_php_test(subprojectnextprevious)
set_property(TEST subprojectnextprevious APPEND PROPERTY DEPENDS hidecolumns)

add_php_test(excludesubprojects)
set_property(TEST excludesubprojects APPEND PROPERTY DEPENDS subprojectnextprevious)

add_php_test(testhistory)
set_property(TEST testhistory APPEND PROPERTY DEPENDS subprojectnextprevious)

add_php_test(expectedandmissing)
set_property(TEST expectedandmissing APPEND PROPERTY DEPENDS testhistory)

add_php_test(externallinksfromtests)
set_property(TEST externallinksfromtests APPEND PROPERTY DEPENDS expectedandmissing)

add_php_test(timesummary)
set_property(TEST timesummary APPEND PROPERTY DEPENDS externallinksfromtests)

add_php_test(buildgetdate)
set_property(TEST buildgetdate APPEND PROPERTY DEPENDS timesummary)

add_php_test(replacebuild)
set_property(TEST replacebuild APPEND PROPERTY DEPENDS buildgetdate)

add_php_test(sequenceindependence)
set_property(TEST sequenceindependence APPEND PROPERTY DEPENDS replacebuild)

add_php_test(crosssubprojectcoverage)
set_property(TEST crosssubprojectcoverage APPEND PROPERTY DEPENDS sequenceindependence)

add_php_test(aggregatesubprojectcoverage)
set_property(TEST aggregatesubprojectcoverage APPEND PROPERTY DEPENDS crosssubprojectcoverage)

add_php_test(filtertestlabels)
set_property(TEST filtertestlabels APPEND PROPERTY DEPENDS aggregatesubprojectcoverage)

add_php_test(dynamicanalysissummary)
set_property(TEST dynamicanalysissummary APPEND PROPERTY DEPENDS filtertestlabels)

add_php_test(viewsubprojects)
set_property(TEST viewsubprojects APPEND PROPERTY DEPENDS dynamicanalysissummary)

add_php_test(truncateoutput)
set_property(TEST truncateoutput APPEND PROPERTY DEPENDS viewsubprojects)

add_php_test(csvexport)
set_property(TEST csvexport APPEND PROPERTY DEPENDS truncateoutput)

add_php_test(uniquediffs)
set_property(TEST uniquediffs APPEND PROPERTY DEPENDS csvexport)

add_php_test(imagecomparison)
set_property(TEST imagecomparison APPEND PROPERTY DEPENDS uniquediffs)

add_php_test(createprojectpermissions)
set_property(TEST createprojectpermissions APPEND PROPERTY DEPENDS imagecomparison)

add_php_test(testgraphpermissions)
set_property(TEST testgraphpermissions APPEND PROPERTY DEPENDS createprojectpermissions)

add_php_test(pdoexecutelogserrors)
set_property(TEST pdoexecutelogserrors APPEND PROPERTY DEPENDS testgraphpermissions)

add_php_test(revisionfilteracrossdates)
set_property(TEST revisionfilteracrossdates APPEND PROPERTY DEPENDS actualtrilinossubmission)

add_php_test(timeoutsandmissingtests)
set_property(TEST timeoutsandmissingtests APPEND PROPERTY DEPENDS pdoexecutelogserrors)

add_php_test(disabledtests)
set_property(TEST disabledtests APPEND PROPERTY DEPENDS timeoutsandmissingtests)

add_php_test(multiplesubprojects)
set_property(TEST multiplesubprojects APPEND PROPERTY DEPENDS disabledtests)

add_php_test(authtoken)
set_property(TEST authtoken APPEND PROPERTY DEPENDS multiplesubprojects)

add_php_test(limitedbuilds)
set_property(TEST limitedbuilds APPEND PROPERTY DEPENDS authtoken)

add_php_test(managemeasurements)
set_property(TEST managemeasurements APPEND PROPERTY DEPENDS limitedbuilds)

add_php_test(coveragedirectories)
set_property(TEST coveragedirectories APPEND PROPERTY DEPENDS managemeasurements)

add_php_test(outputcolor)
set_property(TEST outputcolor APPEND PROPERTY DEPENDS coveragedirectories)

add_php_test(timestatus)
set_property(TEST timestatus APPEND PROPERTY DEPENDS outputcolor)

add_php_test(bazeljson)
set_property(TEST bazeljson APPEND PROPERTY DEPENDS timestatus)

add_php_test(buildrelationship)
set_property(TEST buildrelationship APPEND PROPERTY DEPENDS bazeljson)

add_php_test(submission_assign_buildid)
set_property(TEST submission_assign_buildid APPEND PROPERTY DEPENDS buildrelationship)

add_php_test(donehandler)
set_property(TEST donehandler APPEND PROPERTY DEPENDS submission_assign_buildid)

add_php_test(expiredbuildrules)
set_property(TEST expiredbuildrules APPEND PROPERTY DEPENDS donehandler)

add_php_test(filterblocks)
set_property(TEST filterblocks APPEND PROPERTY DEPENDS expiredbuildrules)

add_php_test(indexnextprevious)
set_property(TEST indexnextprevious APPEND PROPERTY DEPENDS filterblocks)

add_php_test(putdynamicbuilds)
set_property(TEST putdynamicbuilds APPEND PROPERTY DEPENDS indexnextprevious)

add_php_test(commitauthornotification)
set_property(TEST commitauthornotification APPEND PROPERTY DEPENDS putdynamicbuilds)

add_php_test(testimages)
set_property(TEST testimages APPEND PROPERTY DEPENDS commitauthornotification)

add_php_test(namedmeasurements)
set_property(TEST namedmeasurements APPEND PROPERTY DEPENDS testimages)

add_php_test(longbuildname)
set_property(TEST longbuildname APPEND PROPERTY DEPENDS namedmeasurements)

add_php_test(multiplelabelsfortests)
set_property(TEST multiplelabelsfortests APPEND PROPERTY DEPENDS longbuildname)

add_php_test(subprojecttestfilters)
set_property(TEST subprojecttestfilters APPEND PROPERTY DEPENDS multiplelabelsfortests)

add_php_test(starttimefromnotes)
set_property(TEST starttimefromnotes APPEND PROPERTY DEPENDS subprojecttestfilters)

add_php_test(starttimefromupload)
set_property(TEST starttimefromupload APPEND PROPERTY DEPENDS starttimefromnotes)

add_php_test(querytestsfilterlabels)
set_property(TEST querytestsfilterlabels APPEND PROPERTY DEPENDS starttimefromupload)

add_php_test(lotsofsubprojects)
set_property(TEST lotsofsubprojects APPEND PROPERTY DEPENDS querytestsfilterlabels)

add_php_test(querytestsrevisionfilter)
set_property(TEST querytestsrevisionfilter APPEND PROPERTY DEPENDS lotsofsubprojects)

add_php_test(redundanttests)
set_property(TEST redundanttests APPEND PROPERTY DEPENDS querytestsrevisionfilter)

add_php_test(notesparsererrormessages)
set_property(TEST notesparsererrormessages APPEND PROPERTY DEPENDS redundanttests)

add_laravel_test(/Feature/SubProjectDependencies)
set_property(TEST /Feature/SubProjectDependencies APPEND PROPERTY DEPENDS notesparsererrormessages)

add_php_test(misassignedconfigure)
set_property(TEST misassignedconfigure APPEND PROPERTY DEPENDS /Feature/SubProjectDependencies)

add_subdirectory(ctest)
add_subdirectory(autoremovebuilds)
