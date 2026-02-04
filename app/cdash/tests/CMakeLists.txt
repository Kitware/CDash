# We define these here so they can be referenced in functions below.
cdash_install(install_1 "")
cdash_install(install_2 "")
cdash_install(install_clean_database testing_clean)

# function to add a new PHP based coverage test to CDash
function(add_php_test TestName)
  add_test(
    NAME ${TestName}
    COMMAND ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_${TestName}.php
  )
  set_tests_properties(
    ${TestName} PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
    PASS_REGULAR_EXPRESSION ".*OK.*"
    ATTACHED_FILES_ON_FAIL "${CDash_SOURCE_DIR}/storage/logs/cdash.log"
  )
endfunction()

function(add_legacy_unit_test TestName)
  add_test(
    NAME ${TestName}
    COMMAND
      ${PHP_EXE}
      ${phpunit_extra_arg}
      ${PHPUNIT}
      -c ${CDash_SOURCE_DIR}/phpunit.xml
      --bootstrap ${testing_dir}/bootstrap.php
      ${testing_dir}/case/${TestName}Test.php
  )
  set_tests_properties(
    ${TestName} PROPERTIES
    ATTACHED_FILES_ON_FAIL "${CDash_SOURCE_DIR}/storage/logs/cdash.log"
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
  set_tests_properties(
    ${TestName} PROPERTIES
    ATTACHED_FILES_ON_FAIL "${CDash_SOURCE_DIR}/storage/logs/cdash.log"
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
  set_tests_properties(
    ${TestName} PROPERTIES
    ATTACHED_FILES_ON_FAIL "${CDash_SOURCE_DIR}/storage/logs/cdash.log"
  )
endfunction()

# Tests which run entirely within a transaction, leaving no trace in the database.
function(add_feature_test_in_transaction TestName)
  add_laravel_test(${TestName})
  set_property(TEST ${TestName} APPEND PROPERTY DEPENDS install_clean_database)
  set_tests_properties(
    ${TestName} PROPERTIES
    ATTACHED_FILES_ON_FAIL "${CDash_SOURCE_DIR}/storage/logs/cdash.log"
    ENVIRONMENT "DB_DATABASE=testing_clean"
  )
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

add_unit_test(/Unit/ControllerNameTest)

add_unit_test(/Unit/FillableAttributesTest)

add_unit_test(/Unit/Validators/PasswordTest)

add_unit_test(/Unit/Middleware/InternalTest)

# TODO: Is this test useful now?
add_legacy_unit_test(/PHPUnitTest)

add_legacy_unit_test(/CDash/ServiceContainer)

add_legacy_unit_test(/CDash/LinkifyCompilerOutput)

add_unit_test(/Unit/Console/Command/ValidateXmlCommandTest)

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
set_tests_properties(/Feature/CDashTest PROPERTIES DEPENDS install_1)

add_legacy_unit_test(/CDash/Model/BuildRelationship)
set_tests_properties(/CDash/Model/BuildRelationship PROPERTIES DEPENDS /Feature/CDashTest)

add_legacy_unit_test(/CDash/MultipleSubprojectsEmail)
set_tests_properties(/CDash/MultipleSubprojectsEmail PROPERTIES DEPENDS /CDash/Model/BuildRelationship)

add_legacy_unit_test(/CDash/NightlyTime)
set_tests_properties(/CDash/NightlyTime PROPERTIES DEPENDS /CDash/MultipleSubprojectsEmail)

add_legacy_unit_test(/CDash/TestUseCase)
set_tests_properties(/CDash/TestUseCase PROPERTIES DEPENDS /CDash/NightlyTime)

add_legacy_unit_test(/CDash/BuildUseCase)
set_tests_properties(/CDash/BuildUseCase PROPERTIES DEPENDS /CDash/TestUseCase)

add_legacy_unit_test(/CDash/ConfigUseCase)
set_tests_properties(/CDash/ConfigUseCase PROPERTIES DEPENDS /CDash/BuildUseCase)

add_legacy_unit_test(/CDash/UpdateUseCase)
set_tests_properties(/CDash/UpdateUseCase PROPERTIES DEPENDS /CDash/ConfigUseCase)

add_legacy_unit_test(/CDash/XmlHandler/BuildHandler)
set_tests_properties(/CDash/XmlHandler/BuildHandler PROPERTIES DEPENDS /CDash/UpdateUseCase)

add_legacy_unit_test(/CDash/XmlHandler/ConfigureHandler)
set_tests_properties(/CDash/XmlHandler/ConfigureHandler PROPERTIES DEPENDS /CDash/XmlHandler/BuildHandler)

add_legacy_unit_test(/CDash/XmlHandler/DynamicAnalysisHandler)
set_tests_properties(/CDash/XmlHandler/DynamicAnalysisHandler PROPERTIES DEPENDS /CDash/XmlHandler/ConfigureHandler)

add_legacy_unit_test(/CDash/XmlHandler/TestingHandler)
set_tests_properties(/CDash/XmlHandler/TestingHandler PROPERTIES DEPENDS /CDash/XmlHandler/DynamicAnalysisHandler)

add_legacy_unit_test(/CDash/XmlHandler/UpdateHandler)
set_tests_properties(/CDash/XmlHandler/UpdateHandler PROPERTIES DEPENDS /CDash/XmlHandler/TestingHandler)

###################################################################################################
# Tests in this section are expected to run against the testing_clean database in transactions,
# such that the database is empty at any given point.

add_feature_test_in_transaction(/Feature/LoginAndRegistration)

add_feature_test_in_transaction(/Feature/ProjectPermissions)

add_feature_test_in_transaction(/Feature/SlowPageTest)

add_feature_test_in_transaction(/Feature/GitHubWebhook)

add_feature_test_in_transaction(/Feature/GlobalBannerTest)

add_feature_test_in_transaction(/Feature/GraphQL/BuildTypeTest)

add_feature_test_in_transaction(/Feature/ProjectInvitationAcceptanceTest)

add_feature_test_in_transaction(/Feature/GraphQL/FilterTest)

add_feature_test_in_transaction(/Feature/GraphQL/QueryTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/UserTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/ProjectTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/SiteTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/ConfigureTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/TestTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/TestMeasurementTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/NoteTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/CoverageTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/DynamicAnalysisTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/DynamicAnalysisDefectTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/ProjectInvitationTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/LabelTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/TargetTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/BuildCommandTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/BuildCommandOutputTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/UpdateTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/UpdateFileTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/PinnedTestMeasurementTypeTest)

add_feature_test_in_transaction(/Feature/RouteAccessTest)

add_feature_test_in_transaction(/Feature/Monitor)

add_feature_test_in_transaction(/Feature/PasswordRotation)

add_feature_test_in_transaction(/Feature/PurgeUnusedProjectsCommand)

add_feature_test_in_transaction(/Feature/Jobs/PruneJobsTest)

add_feature_test_in_transaction(/Feature/Jobs/PruneAuthTokensTest)

add_feature_test_in_transaction(/Feature/UserCommand)

add_feature_test_in_transaction(/Feature/RemoteWorkers)
set_property(TEST /Feature/RemoteWorkers APPEND PROPERTY
    DISABLED "$<NOT:$<STREQUAL:${CDASH_STORAGE_TYPE},local>>"
)

add_feature_test_in_transaction(/Feature/Jobs/NotifyExpiringAuthTokensTest)

add_feature_test_in_transaction(/Feature/Jobs/PruneUploadsTest)

add_feature_test_in_transaction(/Feature/Mail/AuthTokenExpiringTest)

add_feature_test_in_transaction(/Feature/Mail/AuthTokenExpiredTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/CreateProjectTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/UpdateSiteDescriptionTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/InviteToProjectTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/RevokeProjectInvitationTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/ChangeProjectRoleTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/RemoveProjectUserTest)

add_feature_test_in_transaction(/Feature/Traits/UpdatesSiteInformationTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/ChangeGlobalRoleTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/JoinProjectTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/CreateGlobalInvitationTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/RevokeGlobalInvitationTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/CreatePinnedTestMeasurementTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/DeletePinnedTestMeasurementTest)

add_feature_test_in_transaction(/Feature/GlobalInvitationAcceptanceTest)

add_feature_test_in_transaction(/Feature/GraphQL/GlobalInvitationTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/RemoveUserTest)

add_feature_test_in_transaction(/Feature/Jobs/PruneBuildsTest)

add_feature_test_in_transaction(/Feature/Jobs/PruneEmailsTest)

add_feature_test_in_transaction(/Feature/Services/ProjectServiceTest)

add_feature_test_in_transaction(/Feature/AutoRemoveBuildsCommand)

###################################################################################################
# Tests from this point to the next cdash_install() are expected to be completely independent.
# Tests may wipe the database, provided that they use RUN_SERIAL. All tests in this section should
# depend upon the last test prior to this section.

add_legacy_unit_test(/CDash/Lib/Repository/GitHub)
set_tests_properties(/CDash/Lib/Repository/GitHub PROPERTIES
    DEPENDS /CDash/XmlHandler/UpdateHandler
    DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>"
)

add_legacy_unit_test(/CDash/Messaging/Subscription/CommitAuthorSubscriptionBuilder)
set_tests_properties(/CDash/Messaging/Subscription/CommitAuthorSubscriptionBuilder PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Subscription/UserSubscriptionBuilder)
set_tests_properties(/CDash/Messaging/Subscription/UserSubscriptionBuilder PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/UpdateErrorTopic)
set_tests_properties(/CDash/Messaging/Topic/UpdateErrorTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Service/RepositoryService)
set_tests_properties(/CDash/Service/RepositoryService PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

# Set this to RUN_SERIAL as we are changing the config and don't want to clobber other tests.
# Only requires exclusive .env access.
add_feature_test(/Feature/SubmissionValidation)
set_property(TEST /Feature/SubmissionValidation APPEND PROPERTY RUN_SERIAL TRUE)

# Technically this test doesn't have to run serially.  It just needs to have exclusive access to the .env
add_feature_test(/Feature/LdapIntegration)
set_property(TEST /Feature/LdapIntegration APPEND PROPERTY RUN_SERIAL TRUE)

add_feature_test(/Feature/Submission/Instrumentation/BuildInstrumentationTest)

add_feature_test(/Feature/Submission/Tests/TestXMLTest)

add_browser_test(/Browser/Pages/SitesIdPageTest)

add_browser_test(/Browser/Pages/ProjectSitesPageTest)

add_browser_test(/Browser/Pages/ProjectMembersPageTest)

add_browser_test(/Browser/Pages/ProjectBuildsPageTest)

add_browser_test(/Browser/Pages/CreateProjectPageTest)

add_browser_test(/Browser/Pages/ProjectsPageTest)
set_property(TEST /Browser/Pages/ProjectsPageTest APPEND PROPERTY RUN_SERIAL TRUE)

add_legacy_unit_test(/CDash/Database)
set_tests_properties(/CDash/Database PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/AuthoredTopic)
set_tests_properties(/CDash/Messaging/Topic/AuthoredTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/BuildErrorTopic)
set_tests_properties(/CDash/Messaging/Topic/BuildErrorTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/ConfigureTopic)
set_tests_properties(/CDash/Messaging/Topic/ConfigureTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/DynamicAnalysisTopic)
set_tests_properties(/CDash/Messaging/Topic/DynamicAnalysisTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/EmailSentTopic)
set_tests_properties(/CDash/Messaging/Topic/EmailSentTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/FixedTopic)
set_tests_properties(/CDash/Messaging/Topic/FixedTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/MissingTestTopic)
set_tests_properties(/CDash/Messaging/Topic/MissingTestTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/TestFailureTopic)
set_tests_properties(/CDash/Messaging/Topic/TestFailureTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_legacy_unit_test(/CDash/Messaging/Topic/TopicDecorator)
set_tests_properties(/CDash/Messaging/Topic/TopicDecorator PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_browser_test(/Browser/Pages/UsersPageTest)

add_browser_test(/Browser/Pages/BuildFilesPageTest)

add_browser_test(/Browser/Pages/BuildNotesPageTest)

add_browser_test(/Browser/Pages/BuildTargetsPageTest)

add_browser_test(/Browser/Pages/BuildTestsPageTest)

add_browser_test(/Browser/Pages/BuildConfigurePageTest)

add_browser_test(/Browser/Pages/BuildCoveragePageTest)

add_php_test(image)
set_tests_properties(image PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(changeid)
set_tests_properties(changeid PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(consistenttestingday)
set_tests_properties(consistenttestingday PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(numericupdate)
set_tests_properties(numericupdate PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(attachedfiles)
set_tests_properties(attachedfiles PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(subprojectorder)
set_tests_properties(subprojectorder PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(dynamicanalysislogs)
set_tests_properties(dynamicanalysislogs PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(dynamicanalysisdefectlongtype)
set_tests_properties(dynamicanalysisdefectlongtype PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(configureappend)
set_tests_properties(configureappend PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(filterbuilderrors)
set_tests_properties(filterbuilderrors PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(buildproperties)
set_tests_properties(buildproperties PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(issuecreation)
set_tests_properties(issuecreation PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(junithandler)
set_tests_properties(junithandler PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(configurewarnings)
set_tests_properties(configurewarnings PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_php_test(lotsofsubprojects)
set_tests_properties(lotsofsubprojects PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

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
  lotsofsubprojects
)


add_php_test(compressedtest)
set_tests_properties(compressedtest PROPERTIES DEPENDS install_2)

add_php_test(createpublicdashboard)
set_tests_properties(createpublicdashboard PROPERTIES DEPENDS compressedtest)

add_php_test(email)
set_tests_properties(email PROPERTIES DEPENDS createpublicdashboard)

add_php_test(projectwebpage)
set_tests_properties(projectwebpage PROPERTIES DEPENDS email)

add_php_test(subproject)
set_tests_properties(subproject PROPERTIES DEPENDS projectwebpage)

add_php_test(actualtrilinossubmission)
set_tests_properties(actualtrilinossubmission PROPERTIES
    TIMEOUT 1800 # Slow tests that need more time in CI.
    DEPENDS subproject
)

add_php_test(summaryemail)
set_tests_properties(summaryemail PROPERTIES DEPENDS actualtrilinossubmission)

add_php_test(aggregatecoverage)
set_tests_properties(aggregatecoverage PROPERTIES DEPENDS summaryemail)

add_php_test(buildconfigure)
set_tests_properties(buildconfigure PROPERTIES DEPENDS aggregatecoverage)

add_php_test(buildgrouprule)
set_tests_properties(buildgrouprule PROPERTIES DEPENDS buildconfigure)

add_php_test(buildoverview)
set_tests_properties(buildoverview PROPERTIES DEPENDS buildgrouprule)

add_php_test(committerinfo)
set_tests_properties(committerinfo PROPERTIES DEPENDS buildoverview)

add_php_test(displayimage)
set_tests_properties(displayimage PROPERTIES DEPENDS committerinfo)

add_php_test(projectindb)
set_tests_properties(projectindb PROPERTIES DEPENDS displayimage)

add_php_test(projectmodel)
set_tests_properties(projectmodel PROPERTIES DEPENDS projectindb)

add_php_test(querytests)
set_tests_properties(querytests PROPERTIES DEPENDS projectmodel)

add_php_test(testoverview)
set_tests_properties(testoverview PROPERTIES DEPENDS querytests)

add_php_test(viewconfigure)
set_tests_properties(viewconfigure PROPERTIES DEPENDS testoverview)

add_php_test(viewdynamicanalysis)
set_tests_properties(viewdynamicanalysis PROPERTIES DEPENDS viewconfigure)

add_php_test(viewdynamicanalysisfile)
set_tests_properties(viewdynamicanalysisfile PROPERTIES DEPENDS viewdynamicanalysis)

add_php_test(buildmodel)
set_tests_properties(buildmodel PROPERTIES DEPENDS viewdynamicanalysisfile)

add_php_test(projectxmlsequence)
set_tests_properties(projectxmlsequence PROPERTIES DEPENDS buildmodel)

add_php_test(uploadfile)
set_tests_properties(uploadfile PROPERTIES DEPENDS projectxmlsequence)

add_php_test(actualbranchcoverage)
set_tests_properties(actualbranchcoverage PROPERTIES DEPENDS uploadfile)

add_php_test(unparsedsubmissionshonorbuildid)
set_tests_properties(unparsedsubmissionshonorbuildid PROPERTIES DEPENDS actualbranchcoverage)

add_php_test(multicoverage)
set_tests_properties(multicoverage PROPERTIES DEPENDS unparsedsubmissionshonorbuildid)

add_php_test(javajsoncoverage)
set_tests_properties(javajsoncoverage PROPERTIES DEPENDS multicoverage)

add_php_test(jscovercoverage)
set_tests_properties(jscovercoverage PROPERTIES DEPENDS javajsoncoverage)

add_php_test(opencovercoverage)
set_tests_properties(opencovercoverage PROPERTIES DEPENDS jscovercoverage)

add_php_test(buildfailuredetails)
set_tests_properties(buildfailuredetails PROPERTIES DEPENDS opencovercoverage)

add_php_test(builddetails)
set_tests_properties(builddetails PROPERTIES DEPENDS buildfailuredetails)

add_php_test(updateappend)
set_tests_properties(updateappend PROPERTIES DEPENDS builddetails)

add_php_test(notesapi)
set_tests_properties(notesapi PROPERTIES DEPENDS updateappend)

add_php_test(usernotes)
set_tests_properties(usernotes PROPERTIES DEPENDS notesapi)

add_php_test(hidecolumns)
set_tests_properties(hidecolumns PROPERTIES DEPENDS usernotes)

add_php_test(subprojectnextprevious)
set_tests_properties(subprojectnextprevious PROPERTIES DEPENDS hidecolumns)

add_php_test(excludesubprojects)
set_tests_properties(excludesubprojects PROPERTIES DEPENDS subprojectnextprevious)

add_php_test(testhistory)
set_tests_properties(testhistory PROPERTIES DEPENDS subprojectnextprevious)

add_php_test(expectedandmissing)
set_tests_properties(expectedandmissing PROPERTIES DEPENDS testhistory)

add_php_test(externallinksfromtests)
set_tests_properties(externallinksfromtests PROPERTIES DEPENDS expectedandmissing)

add_php_test(timesummary)
set_tests_properties(timesummary PROPERTIES DEPENDS externallinksfromtests)

add_php_test(buildgetdate)
set_tests_properties(buildgetdate PROPERTIES DEPENDS timesummary)

add_php_test(replacebuild)
set_tests_properties(replacebuild PROPERTIES DEPENDS buildgetdate)

add_php_test(sequenceindependence)
set_tests_properties(sequenceindependence PROPERTIES DEPENDS replacebuild)

add_php_test(crosssubprojectcoverage)
set_tests_properties(crosssubprojectcoverage PROPERTIES DEPENDS sequenceindependence)

add_php_test(aggregatesubprojectcoverage)
set_tests_properties(aggregatesubprojectcoverage PROPERTIES DEPENDS crosssubprojectcoverage)

add_php_test(filtertestlabels)
set_tests_properties(filtertestlabels PROPERTIES DEPENDS aggregatesubprojectcoverage)

add_php_test(dynamicanalysissummary)
set_tests_properties(dynamicanalysissummary PROPERTIES DEPENDS filtertestlabels)

add_php_test(viewsubprojects)
set_tests_properties(viewsubprojects PROPERTIES DEPENDS dynamicanalysissummary)

add_php_test(truncateoutput)
set_tests_properties(truncateoutput PROPERTIES DEPENDS viewsubprojects)

add_php_test(csvexport)
set_tests_properties(csvexport PROPERTIES DEPENDS truncateoutput)

add_php_test(uniquediffs)
set_tests_properties(uniquediffs PROPERTIES DEPENDS csvexport)

add_php_test(imagecomparison)
set_tests_properties(imagecomparison PROPERTIES DEPENDS uniquediffs)

add_php_test(createprojectpermissions)
set_tests_properties(createprojectpermissions PROPERTIES DEPENDS imagecomparison)

add_php_test(testgraphpermissions)
set_tests_properties(testgraphpermissions PROPERTIES DEPENDS createprojectpermissions)

add_php_test(pdoexecutelogserrors)
set_tests_properties(pdoexecutelogserrors PROPERTIES DEPENDS testgraphpermissions)

add_php_test(revisionfilteracrossdates)
set_tests_properties(revisionfilteracrossdates PROPERTIES DEPENDS actualtrilinossubmission)

add_php_test(timeoutsandmissingtests)
set_tests_properties(timeoutsandmissingtests PROPERTIES DEPENDS pdoexecutelogserrors)

add_php_test(disabledtests)
set_tests_properties(disabledtests PROPERTIES DEPENDS timeoutsandmissingtests)

add_php_test(multiplesubprojects)
set_tests_properties(multiplesubprojects PROPERTIES DEPENDS disabledtests)

add_php_test(authtoken)
set_tests_properties(authtoken PROPERTIES DEPENDS multiplesubprojects)

add_php_test(limitedbuilds)
set_tests_properties(limitedbuilds PROPERTIES DEPENDS authtoken)

add_php_test(managemeasurements)
set_tests_properties(managemeasurements PROPERTIES DEPENDS limitedbuilds)

add_php_test(coveragedirectories)
set_tests_properties(coveragedirectories PROPERTIES DEPENDS managemeasurements)

add_php_test(outputcolor)
set_tests_properties(outputcolor PROPERTIES DEPENDS coveragedirectories)

add_php_test(timestatus)
set_tests_properties(timestatus PROPERTIES DEPENDS outputcolor)

add_php_test(bazeljson)
set_tests_properties(bazeljson PROPERTIES DEPENDS timestatus)

add_php_test(buildrelationship)
set_tests_properties(buildrelationship PROPERTIES DEPENDS bazeljson)

add_php_test(submission_assign_buildid)
set_tests_properties(submission_assign_buildid PROPERTIES DEPENDS buildrelationship)

add_php_test(donehandler)
set_tests_properties(donehandler PROPERTIES DEPENDS submission_assign_buildid)

add_php_test(expiredbuildrules)
set_tests_properties(expiredbuildrules PROPERTIES DEPENDS donehandler)

add_php_test(filterblocks)
set_tests_properties(filterblocks PROPERTIES DEPENDS expiredbuildrules)

add_php_test(indexnextprevious)
set_tests_properties(indexnextprevious PROPERTIES DEPENDS filterblocks)

add_php_test(putdynamicbuilds)
set_tests_properties(putdynamicbuilds PROPERTIES DEPENDS indexnextprevious)

add_php_test(commitauthornotification)
set_tests_properties(commitauthornotification PROPERTIES DEPENDS putdynamicbuilds)

add_php_test(testimages)
set_tests_properties(testimages PROPERTIES DEPENDS commitauthornotification)

add_php_test(namedmeasurements)
set_tests_properties(namedmeasurements PROPERTIES DEPENDS testimages)

add_php_test(longbuildname)
set_tests_properties(longbuildname PROPERTIES DEPENDS namedmeasurements)

add_php_test(multiplelabelsfortests)
set_tests_properties(multiplelabelsfortests PROPERTIES DEPENDS longbuildname)

add_php_test(subprojecttestfilters)
set_tests_properties(subprojecttestfilters PROPERTIES DEPENDS multiplelabelsfortests)

add_php_test(starttimefromnotes)
set_tests_properties(starttimefromnotes PROPERTIES DEPENDS subprojecttestfilters)

add_php_test(starttimefromupload)
set_tests_properties(starttimefromupload PROPERTIES DEPENDS starttimefromnotes)

add_php_test(querytestsfilterlabels)
set_tests_properties(querytestsfilterlabels PROPERTIES DEPENDS starttimefromupload)

add_php_test(querytestsrevisionfilter)
set_tests_properties(querytestsrevisionfilter PROPERTIES DEPENDS querytestsfilterlabels)

add_php_test(redundanttests)
set_tests_properties(redundanttests PROPERTIES DEPENDS querytestsrevisionfilter)

add_php_test(notesparsererrormessages)
set_tests_properties(notesparsererrormessages PROPERTIES DEPENDS redundanttests)

add_laravel_test(/Feature/SubProjectDependencies)
set_tests_properties(/Feature/SubProjectDependencies PROPERTIES DEPENDS notesparsererrormessages)

add_php_test(misassignedconfigure)
set_tests_properties(misassignedconfigure PROPERTIES DEPENDS /Feature/SubProjectDependencies)

add_subdirectory(ctest)
add_subdirectory(autoremovebuilds)
