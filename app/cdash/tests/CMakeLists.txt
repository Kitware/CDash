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
  )
endfunction()

function(add_unit_test TestName)
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

# phpunit tests
set(phpunit_extra_arg "")
if (CDASH_CONFIGURE_HTACCESS_FILE)
    set(phpunit_extra_arg "-d auto_prepend_file=${CMAKE_BINARY_DIR}/prepend_coverage.php")
endif()

###################################################################################################
# Tests from this point to the next cdash_install() are expected to not need database or other
# system resources.

add_laravel_test(/Unit/app/ControllerNameTest)

add_laravel_test(/Unit/app/FillableAttributesTest)

add_laravel_test(/Unit/app/Validators/PasswordTest)

add_laravel_test(/Unit/app/Middleware/InternalTest)

# TODO: Is this test useful now?
add_unit_test(/PHPUnitTest)

add_unit_test(/CDash/ServiceContainer)

add_unit_test(/CDash/LinkifyCompilerOutput)

add_laravel_test(/Unit/app/Console/Command/ValidateXmlCommandTest)

###################################################################################################

cdash_install()
set_tests_properties(install_2 PROPERTIES DEPENDS cypress/e2e/user-profile)

add_laravel_test(/Feature/CDashTest)
set_tests_properties(/Feature/CDashTest PROPERTIES DEPENDS install_2)

add_laravel_test(/Feature/LoginAndRegistration)
set_tests_properties(/Feature/LoginAndRegistration PROPERTIES DEPENDS /Feature/CDashTest)

add_laravel_test(/Feature/ProjectPermissions)
set_tests_properties(/Feature/ProjectPermissions PROPERTIES DEPENDS /Feature/LoginAndRegistration)

add_laravel_test(/Feature/SlowPageTest)
set_tests_properties(/Feature/SlowPageTest PROPERTIES DEPENDS /Feature/ProjectPermissions)

add_laravel_test(/Feature/GitHubWebhook)
set_tests_properties(/Feature/GitHubWebhook PROPERTIES DEPENDS /Feature/SlowPageTest)

add_unit_test(/CDash/BuildUseCase)
set_tests_properties(/CDash/BuildUseCase PROPERTIES DEPENDS /Feature/GitHubWebhook)

add_unit_test(/CDash/ConfigUseCase)
set_tests_properties(/CDash/ConfigUseCase PROPERTIES DEPENDS /CDash/BuildUseCase)

add_unit_test(/CDash/Lib/Repository/GitHub)
set_tests_properties(/CDash/Lib/Repository/GitHub PROPERTIES
    DEPENDS /CDash/ConfigUseCase
    DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>"
)

add_unit_test(/CDash/Messaging/Subscription/CommitAuthorSubscriptionBuilder)
set_tests_properties(/CDash/Messaging/Subscription/CommitAuthorSubscriptionBuilder PROPERTIES DEPENDS /CDash/Lib/Repository/GitHub)

add_unit_test(/CDash/Messaging/Subscription/UserSubscriptionBuilder)
set_tests_properties(/CDash/Messaging/Subscription/UserSubscriptionBuilder PROPERTIES DEPENDS /CDash/Messaging/Subscription/CommitAuthorSubscriptionBuilder)

add_unit_test(/CDash/Messaging/Topic/UpdateErrorTopic)
set_tests_properties(/CDash/Messaging/Topic/UpdateErrorTopic PROPERTIES DEPENDS /CDash/Messaging/Subscription/UserSubscriptionBuilder)

add_unit_test(/CDash/Model/BuildError)
set_tests_properties(/CDash/Model/BuildError PROPERTIES DEPENDS /CDash/Messaging/Topic/UpdateErrorTopic)

add_unit_test(/CDash/Model/BuildErrorFilter)
set_tests_properties(/CDash/Model/BuildErrorFilter PROPERTIES DEPENDS /CDash/Model/BuildError)

add_unit_test(/CDash/Model/BuildFailure)
set_tests_properties(/CDash/Model/BuildFailure PROPERTIES DEPENDS /CDash/Model/BuildErrorFilter)

add_unit_test(/CDash/Model/BuildRelationship)
set_tests_properties(/CDash/Model/BuildRelationship PROPERTIES DEPENDS /CDash/Model/BuildFailure)

add_unit_test(/CDash/Model/Repository)
set_tests_properties(/CDash/Model/Repository PROPERTIES DEPENDS /CDash/Model/BuildRelationship)

add_unit_test(/CDash/MultipleSubprojectsEmail)
set_tests_properties(/CDash/MultipleSubprojectsEmail PROPERTIES DEPENDS /CDash/Model/Repository)

add_unit_test(/CDash/NightlyTime)
set_tests_properties(/CDash/NightlyTime PROPERTIES DEPENDS /CDash/MultipleSubprojectsEmail)

add_unit_test(/CDash/Service/RepositoryService)
set_tests_properties(/CDash/Service/RepositoryService PROPERTIES DEPENDS /CDash/NightlyTime)

add_unit_test(/CDash/TestUseCase)
set_tests_properties(/CDash/TestUseCase PROPERTIES DEPENDS /CDash/Service/RepositoryService)

add_unit_test(/CDash/UpdateUseCase)
set_tests_properties(/CDash/UpdateUseCase PROPERTIES DEPENDS /CDash/TestUseCase)

add_unit_test(/CDash/XmlHandler/BuildHandler)
set_tests_properties(/CDash/XmlHandler/BuildHandler PROPERTIES DEPENDS /CDash/UpdateUseCase)

add_unit_test(/CDash/XmlHandler/ConfigureHandler)
set_tests_properties(/CDash/XmlHandler/ConfigureHandler PROPERTIES DEPENDS /CDash/XmlHandler/BuildHandler)

add_unit_test(/CDash/XmlHandler/DynamicAnalysisHandler)
set_tests_properties(/CDash/XmlHandler/DynamicAnalysisHandler PROPERTIES DEPENDS /CDash/XmlHandler/ConfigureHandler)

add_unit_test(/CDash/XmlHandler/TestingHandler)
set_tests_properties(/CDash/XmlHandler/TestingHandler PROPERTIES DEPENDS /CDash/XmlHandler/DynamicAnalysisHandler)

add_unit_test(/CDash/XmlHandler/UpdateHandler)
set_tests_properties(/CDash/XmlHandler/UpdateHandler PROPERTIES DEPENDS /CDash/XmlHandler/TestingHandler)

###################################################################################################
# Tests from this point to the next cdash_install() are expected to be completely independent.
# Tests may wipe the database, provided that they use RUN_SERIAL. All tests in this section should
# depend upon the last test prior to this section.

# Set this to RUN_SERIAL as we are changing the config and don't want to clobber other tests.
# Only requires exclusive .env access.
add_laravel_test(/Feature/SubmissionValidation)
set_tests_properties(/Feature/SubmissionValidation PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Feature/SubmissionValidation PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/ProjectInvitationAcceptanceTest)
set_tests_properties(/Feature/ProjectInvitationAcceptanceTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/FilterTest)
set_tests_properties(/Feature/GraphQL/FilterTest PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Feature/GraphQL/FilterTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/ProjectTypeTest)
set_tests_properties(/Feature/GraphQL/ProjectTypeTest PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Feature/GraphQL/ProjectTypeTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/SiteTypeTest)
set_tests_properties(/Feature/GraphQL/SiteTypeTest PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Feature/GraphQL/SiteTypeTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/BuildTypeTest)
set_tests_properties(/Feature/GraphQL/BuildTypeTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/TestTypeTest)
set_tests_properties(/Feature/GraphQL/TestTypeTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/TestMeasurementTypeTest)
set_tests_properties(/Feature/GraphQL/TestMeasurementTypeTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/NoteTypeTest)
set_tests_properties(/Feature/GraphQL/NoteTypeTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/BuildMeasurementTypeTest)
set_tests_properties(/Feature/GraphQL/BuildMeasurementTypeTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/CoverageTypeTest)
set_tests_properties(/Feature/GraphQL/CoverageTypeTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/UserInvitationTypeTest)
set_tests_properties(/Feature/GraphQL/UserInvitationTypeTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/RouteAccessTest)
set_tests_properties(/Feature/RouteAccessTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/Monitor)
set_tests_properties(/Feature/Monitor PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Feature/Monitor PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/PasswordRotation)
set_tests_properties(/Feature/PasswordRotation PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Browser/Pages/SitesIdPageTest)
set_tests_properties(/Browser/Pages/SitesIdPageTest PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Browser/Pages/SitesIdPageTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

# Technically this test doesn't have to run serially.  It just needs to have exclusive access to the .env
add_laravel_test(/Feature/LdapIntegration)
set_tests_properties(/Feature/LdapIntegration PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Feature/LdapIntegration PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

# TODO: Evaluate whether this test can be independent
add_laravel_test(/Browser/Pages/ProjectSitesPageTest)
set_tests_properties(/Browser/Pages/ProjectSitesPageTest PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Browser/Pages/ProjectSitesPageTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Browser/Pages/ProjectMembersPageTest)
set_tests_properties(/Browser/Pages/ProjectMembersPageTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/PurgeUnusedProjectsCommand)
set_tests_properties(/Feature/PurgeUnusedProjectsCommand PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Feature/PurgeUnusedProjectsCommand PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

# This could theoretically conflict with another test removing jobs from the submission tables in the future
add_laravel_test(/Feature/Jobs/PruneJobsTest)
set_tests_properties(/Feature/Jobs/PruneJobsTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/Jobs/PruneAuthTokensTest)
set_tests_properties(/Feature/Jobs/PruneAuthTokensTest PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Feature/Jobs/PruneAuthTokensTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/UserCommand)
set_tests_properties(/Feature/UserCommand PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_unit_test(/CDash/Database)
set_tests_properties(/CDash/Database PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_unit_test(/CDash/Messaging/Topic/AuthoredTopic)
set_tests_properties(/CDash/Messaging/Topic/AuthoredTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_unit_test(/CDash/Messaging/Topic/BuildErrorTopic)
set_tests_properties(/CDash/Messaging/Topic/BuildErrorTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_unit_test(/CDash/Messaging/Topic/ConfigureTopic)
set_tests_properties(/CDash/Messaging/Topic/ConfigureTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_unit_test(/CDash/Messaging/Topic/DynamicAnalysisTopic)
set_tests_properties(/CDash/Messaging/Topic/DynamicAnalysisTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_unit_test(/CDash/Messaging/Topic/EmailSentTopic)
set_tests_properties(/CDash/Messaging/Topic/EmailSentTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_unit_test(/CDash/Messaging/Topic/FixedTopic)
set_tests_properties(/CDash/Messaging/Topic/FixedTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_unit_test(/CDash/Messaging/Topic/MissingTestTopic)
set_tests_properties(/CDash/Messaging/Topic/MissingTestTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_unit_test(/CDash/Messaging/Topic/TestFailureTopic)
set_tests_properties(/CDash/Messaging/Topic/TestFailureTopic PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_unit_test(/CDash/Messaging/Topic/TopicDecorator)
set_tests_properties(/CDash/Messaging/Topic/TopicDecorator PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/RemoteWorkers)
set_tests_properties(/Feature/RemoteWorkers PROPERTIES
    DEPENDS /CDash/XmlHandler/UpdateHandler
    DISABLED "$<NOT:$<STREQUAL:${CDASH_STORAGE_TYPE},local>>"
)

add_laravel_test(/Feature/Jobs/NotifyExpiringAuthTokensTest)
set_tests_properties(/Feature/Jobs/NotifyExpiringAuthTokensTest PROPERTIES RUN_SERIAL TRUE)
set_tests_properties(/Feature/Jobs/NotifyExpiringAuthTokensTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/Jobs/PruneUploadsTest)
set_tests_properties(/Feature/Jobs/PruneUploadsTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/Mail/AuthTokenExpiringTest)
set_tests_properties(/Feature/Mail/AuthTokenExpiringTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/Mail/AuthTokenExpiredTest)
set_tests_properties(/Feature/Mail/AuthTokenExpiredTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/Mutations/UpdateSiteDescriptionTest)
set_tests_properties(/Feature/GraphQL/Mutations/UpdateSiteDescriptionTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/Mutations/InviteToProjectTest)
set_tests_properties(/Feature/GraphQL/Mutations/InviteToProjectTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/Mutations/RevokeInvitationTest)
set_tests_properties(/Feature/GraphQL/Mutations/RevokeInvitationTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/GraphQL/Mutations/ChangeProjectRoleTest)
set_tests_properties(/Feature/GraphQL/Mutations/ChangeProjectRoleTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

add_laravel_test(/Feature/Traits/UpdatesSiteInformationTest)
set_tests_properties(/Feature/Traits/UpdatesSiteInformationTest PROPERTIES DEPENDS /CDash/XmlHandler/UpdateHandler)

###################################################################################################

cdash_install()
set_property(TEST install_3 PROPERTY DEPENDS
  /Feature/SubmissionValidation
  /Feature/ProjectInvitationAcceptanceTest
  /Feature/GraphQL/FilterTest
  /Feature/GraphQL/ProjectTypeTest
  /Feature/GraphQL/SiteTypeTest
  /Feature/GraphQL/BuildTypeTest
  /Feature/GraphQL/TestTypeTest
  /Feature/GraphQL/TestMeasurementTypeTest
  /Feature/GraphQL/NoteTypeTest
  /Feature/GraphQL/BuildMeasurementTypeTest
  /Feature/GraphQL/CoverageTypeTest
  /Feature/GraphQL/UserInvitationTypeTest
  /Feature/RouteAccessTest
  /Feature/Monitor
  /Feature/PasswordRotation
  /Browser/Pages/SitesIdPageTest
  /Feature/LdapIntegration
  /Browser/Pages/ProjectSitesPageTest
  /Browser/Pages/ProjectMembersPageTest
  /Feature/PurgeUnusedProjectsCommand
  /Feature/Jobs/PruneJobsTest
  /Feature/Jobs/PruneAuthTokensTest
  /Feature/UserCommand
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
  /Feature/RemoteWorkers
  /Feature/Jobs/NotifyExpiringAuthTokensTest
  /Feature/Jobs/PruneUploadsTest
  /Feature/Mail/AuthTokenExpiringTest
  /Feature/Mail/AuthTokenExpiredTest
  /Feature/GraphQL/Mutations/UpdateSiteDescriptionTest
  /Feature/GraphQL/Mutations/InviteToProjectTest
  /Feature/GraphQL/Mutations/RevokeInvitationTest
  /Feature/GraphQL/Mutations/ChangeProjectRoleTest
  /Feature/Traits/UpdatesSiteInformationTest
)


add_php_test(compressedtest)
set_tests_properties(compressedtest PROPERTIES DEPENDS install_3)

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

add_php_test(upgrade)
set_tests_properties(upgrade PROPERTIES DEPENDS summaryemail)

add_php_test(aggregatecoverage)
set_tests_properties(aggregatecoverage PROPERTIES DEPENDS upgrade)

add_php_test(buildconfigure)
set_tests_properties(buildconfigure PROPERTIES DEPENDS aggregatecoverage)

add_php_test(buildgrouprule)
set_tests_properties(buildgrouprule PROPERTIES DEPENDS buildconfigure)

add_php_test(buildoverview)
set_tests_properties(buildoverview PROPERTIES DEPENDS buildgrouprule)

add_php_test(committerinfo)
set_tests_properties(committerinfo PROPERTIES DEPENDS buildoverview)

add_php_test(image)
set_tests_properties(image PROPERTIES DEPENDS committerinfo)

add_php_test(displayimage)
set_tests_properties(displayimage PROPERTIES DEPENDS image)

add_cypress_e2e_test(banner)
set_tests_properties(cypress/e2e/banner PROPERTIES DEPENDS displayimage)

add_php_test(manageprojectroles)
set_tests_properties(manageprojectroles PROPERTIES DEPENDS cypress/e2e/banner)

add_php_test(manageusers)
set_tests_properties(manageusers PROPERTIES DEPENDS manageprojectroles)

add_php_test(projectindb)
set_tests_properties(projectindb PROPERTIES DEPENDS manageusers)

add_php_test(pubproject)
set_tests_properties(pubproject PROPERTIES DEPENDS projectindb)

add_php_test(projectmodel)
set_tests_properties(projectmodel PROPERTIES DEPENDS projectindb)

add_php_test(querytests)
set_tests_properties(querytests PROPERTIES DEPENDS projectmodel)

add_php_test(sitestatistics)
set_tests_properties(sitestatistics PROPERTIES DEPENDS querytests)

add_php_test(testoverview)
set_tests_properties(testoverview PROPERTIES DEPENDS sitestatistics)

add_php_test(userstatistics)
set_tests_properties(userstatistics PROPERTIES DEPENDS testoverview)

add_php_test(viewconfigure)
set_tests_properties(viewconfigure PROPERTIES DEPENDS testoverview)

add_php_test(viewdynamicanalysis)
set_tests_properties(viewdynamicanalysis PROPERTIES DEPENDS viewconfigure)

add_php_test(viewdynamicanalysisfile)
set_tests_properties(viewdynamicanalysisfile PROPERTIES DEPENDS viewdynamicanalysis)

add_php_test(buildmodel)
set_tests_properties(buildmodel PROPERTIES DEPENDS viewdynamicanalysisfile)

add_unit_test(/CDash/Model/PendingSubmissions)
set_tests_properties(/CDash/Model/PendingSubmissions PROPERTIES DEPENDS buildmodel)

add_php_test(projectxmlsequence)
set_tests_properties(projectxmlsequence PROPERTIES DEPENDS /CDash/Model/PendingSubmissions)

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

add_php_test(passwordcomplexity)
set_tests_properties(passwordcomplexity PROPERTIES DEPENDS sequenceindependence)

add_php_test(crosssubprojectcoverage)
set_tests_properties(crosssubprojectcoverage PROPERTIES DEPENDS passwordcomplexity)

add_php_test(aggregatesubprojectcoverage)
set_tests_properties(aggregatesubprojectcoverage PROPERTIES DEPENDS crosssubprojectcoverage)

add_php_test(configurewarnings)
set_tests_properties(configurewarnings PROPERTIES DEPENDS aggregatesubprojectcoverage)

add_php_test(filtertestlabels)
set_tests_properties(filtertestlabels PROPERTIES DEPENDS configurewarnings)

add_php_test(seconds_from_interval)
set_tests_properties(seconds_from_interval PROPERTIES DEPENDS filtertestlabels)

add_php_test(dynamicanalysissummary)
set_tests_properties(dynamicanalysissummary PROPERTIES DEPENDS seconds_from_interval)

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

add_php_test(extracttar)
set_tests_properties(extracttar PROPERTIES DEPENDS testgraphpermissions)

add_php_test(pdoexecutelogserrors)
set_tests_properties(pdoexecutelogserrors PROPERTIES DEPENDS extracttar)

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

add_php_test(junithandler)
set_tests_properties(junithandler PROPERTIES DEPENDS authtoken)

add_php_test(issuecreation)
set_tests_properties(issuecreation PROPERTIES DEPENDS junithandler)

add_php_test(limitedbuilds)
set_tests_properties(limitedbuilds PROPERTIES DEPENDS issuecreation)

add_php_test(managemeasurements)
set_tests_properties(managemeasurements PROPERTIES DEPENDS limitedbuilds)

add_php_test(subprojectemail)
set_tests_properties(subprojectemail PROPERTIES DEPENDS managemeasurements)

add_php_test(coveragedirectories)
set_tests_properties(coveragedirectories PROPERTIES DEPENDS subprojectemail)

add_php_test(outputcolor)
set_tests_properties(outputcolor PROPERTIES DEPENDS coveragedirectories)

add_php_test(buildproperties)
set_tests_properties(buildproperties PROPERTIES DEPENDS outputcolor)

add_php_test(timestatus)
set_tests_properties(timestatus PROPERTIES DEPENDS buildproperties)

add_php_test(bazeljson)
set_tests_properties(bazeljson PROPERTIES DEPENDS timestatus)

add_php_test(filterbuilderrors)
set_tests_properties(filterbuilderrors PROPERTIES DEPENDS bazeljson)

add_php_test(buildrelationship)
set_tests_properties(buildrelationship PROPERTIES DEPENDS filterbuilderrors)

add_php_test(submission_assign_buildid)
set_tests_properties(submission_assign_buildid PROPERTIES DEPENDS buildrelationship)

add_php_test(donehandler)
set_tests_properties(donehandler PROPERTIES DEPENDS submission_assign_buildid)

add_php_test(changeid)
set_tests_properties(changeid PROPERTIES DEPENDS donehandler)

add_php_test(updateonlyuserstats)
set_tests_properties(updateonlyuserstats PROPERTIES DEPENDS changeid)

add_php_test(expiredbuildrules)
set_tests_properties(expiredbuildrules PROPERTIES DEPENDS updateonlyuserstats)

add_php_test(filterblocks)
set_tests_properties(filterblocks PROPERTIES DEPENDS expiredbuildrules)

add_php_test(indexnextprevious)
set_tests_properties(indexnextprevious PROPERTIES DEPENDS filterblocks)

add_php_test(putdynamicbuilds)
set_tests_properties(putdynamicbuilds PROPERTIES DEPENDS indexnextprevious)

add_php_test(commitauthornotification)
set_tests_properties(commitauthornotification PROPERTIES DEPENDS putdynamicbuilds)

add_php_test(subscribeprojectshowlabels)
set_tests_properties(subscribeprojectshowlabels PROPERTIES DEPENDS putdynamicbuilds)

add_php_test(consistenttestingday)
set_tests_properties(consistenttestingday PROPERTIES DEPENDS subscribeprojectshowlabels)

add_php_test(numericupdate)
set_tests_properties(numericupdate PROPERTIES DEPENDS consistenttestingday)

add_php_test(attachedfiles)
set_tests_properties(attachedfiles PROPERTIES DEPENDS numericupdate)

add_php_test(subprojectorder)
set_tests_properties(subprojectorder PROPERTIES DEPENDS attachedfiles)

add_php_test(testimages)
set_tests_properties(testimages PROPERTIES DEPENDS subprojectorder)

add_php_test(dynamicanalysislogs)
set_tests_properties(dynamicanalysislogs PROPERTIES DEPENDS testimages)

add_php_test(namedmeasurements)
set_tests_properties(namedmeasurements PROPERTIES DEPENDS dynamicanalysislogs)

add_php_test(longbuildname)
set_tests_properties(longbuildname PROPERTIES DEPENDS namedmeasurements)

add_php_test(multiplelabelsfortests)
set_tests_properties(multiplelabelsfortests PROPERTIES DEPENDS longbuildname)

add_php_test(subprojecttestfilters)
set_tests_properties(subprojecttestfilters PROPERTIES DEPENDS multiplelabelsfortests)

add_php_test(dynamicanalysisdefectlongtype)
set_tests_properties(dynamicanalysisdefectlongtype PROPERTIES DEPENDS multiplelabelsfortests)

add_php_test(starttimefromnotes)
set_tests_properties(starttimefromnotes PROPERTIES DEPENDS dynamicanalysisdefectlongtype)

add_php_test(starttimefromupload)
set_tests_properties(starttimefromupload PROPERTIES DEPENDS starttimefromnotes)

add_php_test(querytestsfilterlabels)
set_tests_properties(querytestsfilterlabels PROPERTIES DEPENDS starttimefromupload)

add_php_test(lotsofsubprojects)
set_tests_properties(lotsofsubprojects PROPERTIES DEPENDS querytestsfilterlabels)

add_php_test(querytestsrevisionfilter)
set_tests_properties(querytestsrevisionfilter PROPERTIES DEPENDS lotsofsubprojects)

add_php_test(redundanttests)
set_tests_properties(redundanttests PROPERTIES DEPENDS querytestsrevisionfilter)

add_php_test(configureappend)
set_tests_properties(configureappend PROPERTIES DEPENDS redundanttests)

add_php_test(notesparsererrormessages)
set_tests_properties(notesparsererrormessages PROPERTIES DEPENDS configureappend)

add_laravel_test(/Feature/SubProjectDependencies)
set_tests_properties(/Feature/SubProjectDependencies PROPERTIES DEPENDS notesparsererrormessages)

add_php_test(misassignedconfigure)
set_tests_properties(misassignedconfigure PROPERTIES DEPENDS /Feature/SubProjectDependencies)

add_subdirectory(ctest)
add_subdirectory(autoremovebuilds)
