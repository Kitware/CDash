# We define these here so they can be referenced in functions below.
cdash_install(install_1 "")
cdash_install(install_2 "")
cdash_install(install_clean_database testing_clean)

# function to add a new PHP based test to CDash
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

function(add_laravel_test TestName)
  add_test(
    NAME ${TestName}
    COMMAND
      ${PHP_EXE}
      ${PHPUNIT}
      -c ${CDash_SOURCE_DIR}/phpunit.xml
      ${laravel_testing_dir}/${TestName}.php
  )
  set_tests_properties(
    ${TestName} PROPERTIES
    ATTACHED_FILES_ON_FAIL "${CDash_SOURCE_DIR}/storage/logs/cdash.log"
  )
  set_property(GLOBAL APPEND PROPERTY CDASH_REGISTERED_TEST_FILES "tests${TestName}.php")

  # Storage::fake() empties and reuses the same directory for a given disk in every test process,
  # unless Laravel's parallel testing token is set, in which case it appends "_test_<token>" to the
  # directory.  Give each test its own token so that tests running in parallel never share (or
  # clear) each other's fake disks.  Don't set LARAVEL_PARALLEL_TESTING as well: that would also
  # make Laravel switch each test to its own database, cache prefix, and compiled views directory.
  string(REGEX REPLACE "^/" "" test_token "${TestName}")
  string(MAKE_C_IDENTIFIER "${test_token}" test_token)
  set_property(TEST ${TestName} APPEND PROPERTY ENVIRONMENT "TEST_TOKEN=${test_token}")
endfunction()

# Tests with no dependencies whatsoever
function(add_unit_test TestName)
  add_laravel_test(${TestName})
endfunction()

# Tests with no dependencies on other tests which require the database to be available
function(add_feature_test TestName)
  add_laravel_test(${TestName})
  set_property(TEST ${TestName} APPEND PROPERTY DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)
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
  set_property(TEST ${TestName} APPEND PROPERTY ENVIRONMENT "DB_DATABASE=testing_clean")
  set_tests_properties(
    ${TestName} PROPERTIES
    ATTACHED_FILES_ON_FAIL "${CDash_SOURCE_DIR}/storage/logs/cdash.log"
  )
endfunction()

add_test(
  NAME change_app_url_to_website
  COMMAND sed -i "s#http://localhost:8080#http://website:8080#g" "${CDash_SOURCE_DIR}/.env"
)
set_tests_properties(
  change_app_url_to_website
  PROPERTIES FIXTURES_SETUP APP_URL_WEBSITE
)

add_test(
  NAME change_app_url_to_localhost
  COMMAND sed -i "s#http://website:8080#http://localhost:8080#g" "${CDash_SOURCE_DIR}/.env"
)
set_tests_properties(
  change_app_url_to_localhost
  PROPERTIES FIXTURES_CLEANUP APP_URL_WEBSITE
)

function(add_browser_test TestName)
  add_feature_test(${TestName})
  set_property(TEST ${TestName} APPEND PROPERTY FIXTURES_REQUIRED APP_URL_WEBSITE)
  set_property(TEST ${TestName} APPEND PROPERTY PROCESSORS 2)
endfunction()

###################################################################################################
# Tests from this point to the next cdash_install() are expected to not need database or other
# system resources.

add_unit_test(/Unit/ControllerNameTest)

add_unit_test(/Unit/FillableAttributesTest)

add_unit_test(/Unit/TimezoneConfigTest)

add_unit_test(/Unit/Validators/PasswordTest)

add_unit_test(/Unit/Middleware/InternalTest)

add_unit_test(/Unit/Utils/SubmissionUtilsTest)

add_unit_test(/Unit/Utils/RedirectUtilsTest)

add_unit_test(/Unit/Services/XmlValidationServiceTest)

add_unit_test(/Unit/GraphQL/SchemaValidationTest)

add_unit_test(/Unit/GraphQL/RelationshipDirectivesTest)

add_unit_test(/Unit/GraphQL/Directives/CountHistogramDirectiveTest)

add_unit_test(/Unit/GraphQL/Directives/FilterDirectiveTest)

add_unit_test(/Unit/GraphQL/Directives/FilterableDirectiveTest)

add_unit_test(/Unit/CommonFunctionsTest)

add_laravel_test(/Legacy/Model/BuildTest)

add_laravel_test(/Legacy/Model/RepositoryTest)

###################################################################################################

add_laravel_test(/Feature/CDashTest)
set_tests_properties(/Feature/CDashTest PROPERTIES DEPENDS install_1)

add_laravel_test(/Legacy/MultipleSubprojectsEmailTest)
set_tests_properties(/Legacy/MultipleSubprojectsEmailTest PROPERTIES DEPENDS /Feature/CDashTest)

add_laravel_test(/Legacy/NightlyTimeTest)
set_tests_properties(/Legacy/NightlyTimeTest PROPERTIES DEPENDS /Legacy/MultipleSubprojectsEmailTest)

add_laravel_test(/Legacy/TestUseCaseTest)
set_tests_properties(/Legacy/TestUseCaseTest PROPERTIES DEPENDS /Legacy/NightlyTimeTest)

add_laravel_test(/Legacy/BuildUseCaseTest)
set_tests_properties(/Legacy/BuildUseCaseTest PROPERTIES DEPENDS /Legacy/TestUseCaseTest)

add_laravel_test(/Legacy/ConfigUseCaseTest)
set_tests_properties(/Legacy/ConfigUseCaseTest PROPERTIES DEPENDS /Legacy/BuildUseCaseTest)

add_laravel_test(/Legacy/UpdateUseCaseTest)
set_tests_properties(/Legacy/UpdateUseCaseTest PROPERTIES DEPENDS /Legacy/ConfigUseCaseTest)

add_laravel_test(/Legacy/DynamicAnalysisUseCaseTest)
set_tests_properties(/Legacy/DynamicAnalysisUseCaseTest PROPERTIES DEPENDS /Legacy/UpdateUseCaseTest)

add_laravel_test(/Legacy/XmlHandler/BuildHandlerTest)
set_tests_properties(/Legacy/XmlHandler/BuildHandlerTest PROPERTIES DEPENDS /Legacy/DynamicAnalysisUseCaseTest)

add_laravel_test(/Legacy/XmlHandler/ConfigureHandlerTest)
set_tests_properties(/Legacy/XmlHandler/ConfigureHandlerTest PROPERTIES DEPENDS /Legacy/XmlHandler/BuildHandlerTest)

add_laravel_test(/Legacy/XmlHandler/DynamicAnalysisHandlerTest)
set_tests_properties(/Legacy/XmlHandler/DynamicAnalysisHandlerTest PROPERTIES DEPENDS /Legacy/XmlHandler/ConfigureHandlerTest)

add_laravel_test(/Legacy/XmlHandler/TestingHandlerTest)
set_tests_properties(/Legacy/XmlHandler/TestingHandlerTest PROPERTIES DEPENDS /Legacy/XmlHandler/DynamicAnalysisHandlerTest)

add_laravel_test(/Legacy/XmlHandler/UpdateHandlerTest)
set_tests_properties(/Legacy/XmlHandler/UpdateHandlerTest PROPERTIES DEPENDS /Legacy/XmlHandler/TestingHandlerTest)

###################################################################################################
# Tests in this section are expected to run against the testing_clean database in transactions,
# such that the database is empty at any given point.

add_feature_test_in_transaction(/Feature/LoginAndRegistration)

add_feature_test_in_transaction(/Feature/ProjectPermissions)

add_feature_test_in_transaction(/Feature/SlowPageTest)

add_feature_test_in_transaction(/Feature/IndexSubProjectFiltersTest)

add_feature_test_in_transaction(/Feature/IndexLabelFiltersTest)

add_feature_test_in_transaction(/Feature/GitHubWebhook)

add_feature_test_in_transaction(/Feature/GlobalBannerTest)

add_feature_test_in_transaction(/Feature/SessionMiddlewareTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/BuildTypeTest)

add_feature_test_in_transaction(/Feature/ProjectInvitationAcceptanceTest)

add_feature_test_in_transaction(/Feature/UpdateProjectLogoTest)

add_feature_test_in_transaction(/Feature/ProjectLogoTest)

add_feature_test_in_transaction(/Feature/TestImageAccessTest)

add_feature_test_in_transaction(/Feature/TestFileDownloadTest)

add_feature_test_in_transaction(/Feature/BuildFileDownloadTest)

add_feature_test_in_transaction(/Feature/ProjectNotificationsTest)

add_feature_test_in_transaction(/Feature/GraphQL/FilterTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/QueryTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/UserTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/ProjectTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/SiteTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/ConfigureTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/TestTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/TestImageTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/TestMeasurementTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/NoteTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/CoverageTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/DynamicAnalysisTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/DynamicAnalysisDefectTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/ProjectInvitationTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/LabelTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/TargetTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/BuildCommandTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/BuildCommandOutputTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/BuildMeasurementTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/UpdateTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/UpdateFileTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/PinnedTestMeasurementTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/BuildErrorTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/CommentTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/RepositoryTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/CoverageDiffTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/BuildGroupTypeTest)

add_feature_test_in_transaction(/Feature/RouteAccessTest)

add_feature_test_in_transaction(/Feature/Monitor)

add_feature_test_in_transaction(/Feature/PasswordRotation)

add_feature_test_in_transaction(/Feature/PasswordResetTest)

add_feature_test_in_transaction(/Feature/ProfilePasswordChangeTest)

add_feature_test_in_transaction(/Feature/ProfileEmailChangeTest)

add_feature_test_in_transaction(/Feature/ExternalLoginTest)

add_feature_test_in_transaction(/Feature/Console/PurgeUnusedProjectsCommandTest)

add_feature_test_in_transaction(/Feature/Jobs/PruneJobsTest)

add_feature_test_in_transaction(/Feature/Jobs/PruneSubmissionsTest)

add_feature_test_in_transaction(/Feature/Jobs/PruneAuthTokensTest)

add_feature_test_in_transaction(/Feature/Console/SaveUserCommandTest)

add_feature_test_in_transaction(/Feature/Console/RemoveUserCommandTest)

add_feature_test_in_transaction(/Feature/Console/EmailTestCommandTest)

add_feature_test_in_transaction(/Feature/Jobs/NotifyExpiringAuthTokensTest)

add_feature_test_in_transaction(/Feature/Jobs/PruneUploadsTest)

add_feature_test_in_transaction(/Feature/Mail/AuthTokenExpiringTest)

add_feature_test_in_transaction(/Feature/Mail/AuthTokenExpiredTest)

add_feature_test_in_transaction(/Feature/Mail/InvitedToCdashTest)

add_feature_test_in_transaction(/Feature/Mail/InvitedToProjectTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/CreateProjectTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/UpdateProjectTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/DeleteProjectTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/UpdateSiteDescriptionTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/InviteToProjectTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/RevokeProjectInvitationTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/ChangeProjectRoleTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/RemoveProjectUserTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/ChangeGlobalRoleTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/JoinProjectTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/CreateGlobalInvitationTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/RevokeGlobalInvitationTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/CreatePinnedTestMeasurementTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/DeletePinnedTestMeasurementTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/UpdatePinnedTestMeasurementOrderTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/CreateRepositoryTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/DeleteRepositoryTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/CreateAuthenticationTokenTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/DeleteAuthenticationTokenTest)

add_feature_test_in_transaction(/Feature/GlobalInvitationAcceptanceTest)

add_feature_test_in_transaction(/Feature/GraphQL/Queries/GlobalInvitationTypeTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/RemoveUserTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/CreateCommentTest)

add_feature_test_in_transaction(/Feature/GraphQL/Mutations/CreateCoverageDiffMutationTest)

add_feature_test_in_transaction(/Feature/Jobs/PruneBuildsTest)

add_feature_test_in_transaction(/Feature/Database/ProjectRoleHistoryTest)

add_feature_test_in_transaction(/Feature/Jobs/PruneEmailsTest)

add_feature_test_in_transaction(/Feature/Jobs/ComputeCoverageDifferenceTest)

add_feature_test_in_transaction(/Feature/Jobs/PerformLegacyDailyUpdatesTest)

add_feature_test_in_transaction(/Feature/BuildGroupRuleTest)

add_feature_test_in_transaction(/Feature/ManageOverviewTest)

add_feature_test_in_transaction(/Feature/TestOverviewTest)

add_feature_test_in_transaction(/Feature/DynamicBuildGroupTest)

add_feature_test_in_transaction(/Feature/Services/AuthTokenServiceTest)

add_feature_test_in_transaction(/Feature/Services/ProjectServiceTest)

add_feature_test_in_transaction(/Feature/Services/SiteServiceTest)

add_feature_test_in_transaction(/Feature/Middleware/AuthenticateTokenTest)

add_feature_test_in_transaction(/Feature/Console/AutoRemoveBuildsCommandTest)

add_feature_test_in_transaction(/Feature/Console/QueueSubmissionsCommandTest)

add_feature_test_in_transaction(/Feature/Console/DeleteProjectCommandTest)

add_feature_test_in_transaction(/Feature/Jobs/DeleteProjectTest)

add_feature_test_in_transaction(/Feature/Console/ValidateXmlCommandTest)

add_feature_test_in_transaction(/Feature/Console/ListSubmissionsCommandTest)

add_feature_test_in_transaction(/Feature/Submission/XmlSubmissionTest)

add_feature_test_in_transaction(/Feature/Submission/UnparsedSubmissionTest)

add_feature_test_in_transaction(/Feature/Submission/DeferredSubmissionTest)

add_feature_test_in_transaction(/Feature/Submission/ConcurrentSubmissionTest)

add_feature_test_in_transaction(/Feature/Submission/SubmissionRecordingTest)

add_feature_test_in_transaction(/Feature/Submission/XmlValidationTest)

add_feature_test_in_transaction(/Feature/Submission/Test/TestXMLTest)

add_feature_test_in_transaction(/Feature/Submission/Test/JUnitXMLTest)

add_feature_test_in_transaction(/Feature/Submission/Build/BuildXMLTest)

add_feature_test_in_transaction(/Feature/Submission/CoverageLog/CoverageLogXMLTest)

add_feature_test_in_transaction(/Feature/Submission/Coverage/GcovTarTest)

add_feature_test_in_transaction(/Feature/Submission/Coverage/JavaJSONTarTest)

add_feature_test_in_transaction(/Feature/Submission/Coverage/JSCoverTarTest)

add_feature_test_in_transaction(/Feature/Submission/Coverage/OpenCoverTarTest)

add_feature_test_in_transaction(/Feature/Submission/Configure/ConfigureXMLTest)

add_feature_test_in_transaction(/Feature/Submission/Bazel/BazelJSONTest)

add_feature_test_in_transaction(/Feature/Submission/BuildProperties/BuildPropertiesJSONTest)

add_feature_test_in_transaction(/Feature/Submission/DynamicAnalysis/DynamicAnalysisXMLTest)

add_feature_test_in_transaction(/Feature/Submission/Update/UpdateXMLTest)

add_feature_test_in_transaction(/Feature/Submission/Notes/NotesXMLTest)

add_feature_test_in_transaction(/Feature/Submission/Upload/UploadXMLTest)

add_feature_test_in_transaction(/Feature/Submission/Done/DoneXMLTest)

add_feature_test_in_transaction(/Feature/Submission/SubmissionBackupTest)

add_feature_test_in_transaction(/Feature/Submission/SubmissionOrderTest)

add_feature_test_in_transaction(/Feature/Submission/Project/ProjectXMLTest)

###################################################################################################
# Tests from this point to the next cdash_install() are expected to be completely independent.
# Tests may wipe the database, provided that they use RUN_SERIAL. All tests in this section should
# depend upon the last test prior to this section.

add_laravel_test(/Legacy/Lib/Repository/GitHubTest)
set_tests_properties(/Legacy/Lib/Repository/GitHubTest PROPERTIES
    DEPENDS /Legacy/XmlHandler/UpdateHandlerTest
    DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>"
)

add_laravel_test(/Legacy/Messaging/Subscription/CommitAuthorSubscriptionBuilderTest)
set_tests_properties(/Legacy/Messaging/Subscription/CommitAuthorSubscriptionBuilderTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Subscription/UserSubscriptionBuilderTest)
set_tests_properties(/Legacy/Messaging/Subscription/UserSubscriptionBuilderTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Topic/UpdateErrorTopicTest)
set_tests_properties(/Legacy/Messaging/Topic/UpdateErrorTopicTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Service/RepositoryServiceTest)
set_tests_properties(/Legacy/Service/RepositoryServiceTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_feature_test(/Feature/LdapIntegration)

add_feature_test(/Feature/Submission/CTestSubmissionTest)

add_browser_test(/Browser/Pages/SitesIdPageTest)

add_browser_test(/Browser/Pages/ProjectSitesPageTest)

add_browser_test(/Browser/Pages/ProjectHomePageTest)

add_browser_test(/Browser/Pages/ProjectMembersPageTest)

add_browser_test(/Browser/Pages/ProjectNotificationsPageTest)

add_browser_test(/Browser/Pages/ProjectBuildsPageTest)

add_browser_test(/Browser/Pages/ProjectBuildsPageSortingTest)

add_browser_test(/Browser/Pages/ProjectBuildsPageSettingsTest)

add_browser_test(/Browser/Pages/ProjectBuildsPageAdminTest)

add_browser_test(/Browser/Pages/ProjectBuildsPageFiltersTest)

add_browser_test(/Browser/Pages/QueryTestsPageTest)

add_browser_test(/Browser/Pages/QueryTestsPageSortingTest)

add_browser_test(/Browser/Pages/QueryTestsPageFiltersTest)

add_browser_test(/Browser/Pages/CreateProjectPageTest)

add_browser_test(/Browser/Pages/ProfilePageTest)

add_browser_test(/Browser/Pages/ManageAuthTokensPageTest)

add_browser_test(/Browser/Pages/PasswordResetPageTest)

add_browser_test(/Browser/Pages/ProjectsPageTest)
set_property(TEST /Browser/Pages/ProjectsPageTest APPEND PROPERTY RUN_SERIAL TRUE)

add_laravel_test(/Legacy/DatabaseTest)
set_tests_properties(/Legacy/DatabaseTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Topic/AuthoredTopicTest)
set_tests_properties(/Legacy/Messaging/Topic/AuthoredTopicTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Topic/BuildErrorTopicTest)
set_tests_properties(/Legacy/Messaging/Topic/BuildErrorTopicTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Topic/ConfigureTopicTest)
set_tests_properties(/Legacy/Messaging/Topic/ConfigureTopicTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Topic/DynamicAnalysisTopicTest)
set_tests_properties(/Legacy/Messaging/Topic/DynamicAnalysisTopicTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Topic/EmailSentTopicTest)
set_tests_properties(/Legacy/Messaging/Topic/EmailSentTopicTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Topic/FixedTopicTest)
set_tests_properties(/Legacy/Messaging/Topic/FixedTopicTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Topic/MissingTestTopicTest)
set_tests_properties(/Legacy/Messaging/Topic/MissingTestTopicTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Topic/TestFailureTopicTest)
set_tests_properties(/Legacy/Messaging/Topic/TestFailureTopicTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_laravel_test(/Legacy/Messaging/Topic/TopicDecoratorTest)
set_tests_properties(/Legacy/Messaging/Topic/TopicDecoratorTest PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_browser_test(/Browser/Pages/UsersPageTest)

add_browser_test(/Browser/Pages/BuildFilesPageTest)

add_browser_test(/Browser/Pages/BuildNotesPageTest)

add_browser_test(/Browser/Pages/BuildTargetsPageTest)

add_browser_test(/Browser/Pages/BuildInstrumentationPageTest)

add_browser_test(/Browser/Pages/BuildTestsPageTest)

add_browser_test(/Browser/Pages/BuildConfigurePageTest)

add_browser_test(/Browser/Pages/BuildBuildPageTest)

add_browser_test(/Browser/Pages/BuildCoveragePageTest)

add_browser_test(/Browser/Pages/CoverageFilePageTest)

add_browser_test(/Browser/Pages/BuildDynamicAnalysisIdPageTest)

add_browser_test(/Browser/Pages/BuildSummaryPageTest)

add_browser_test(/Browser/Pages/BuildCommentsPageTest)

add_browser_test(/Browser/Pages/BuildSidebarComponentTest)

add_browser_test(/Browser/Pages/TestsIdPageTest)

add_browser_test(/Browser/Pages/TestOverviewPageTest)

add_browser_test(/Browser/Pages/ProjectSettingsPageTest)

add_browser_test(/Browser/Pages/BuildUpdatePageTest)

add_browser_test(/Browser/Pages/ManageBuildGroupPageTest)

add_browser_test(/Browser/Pages/ManageOverviewPageTest)

add_browser_test(/Browser/Pages/ManageSubProjectPageTest)

add_browser_test(/Browser/Pages/ViewSubProjectsPageTest)

add_php_test(image)
set_tests_properties(image PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_php_test(consistenttestingday)
set_tests_properties(consistenttestingday PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_php_test(subprojectorder)
set_tests_properties(subprojectorder PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_php_test(issuecreation)
set_tests_properties(issuecreation PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

add_php_test(configurewarnings)
set_tests_properties(configurewarnings PROPERTIES DEPENDS /Legacy/XmlHandler/UpdateHandlerTest)

###################################################################################################

set_property(TEST install_2 APPEND PROPERTY DEPENDS
  change_app_url_to_localhost
  /Legacy/Lib/Repository/GitHubTest
  /Legacy/Messaging/Subscription/CommitAuthorSubscriptionBuilderTest
  /Legacy/Messaging/Subscription/UserSubscriptionBuilderTest
  /Legacy/Messaging/Topic/UpdateErrorTopicTest
  /Legacy/Service/RepositoryServiceTest
  /Legacy/DatabaseTest
  /Legacy/Messaging/Topic/AuthoredTopicTest
  /Legacy/Messaging/Topic/BuildErrorTopicTest
  /Legacy/Messaging/Topic/ConfigureTopicTest
  /Legacy/Messaging/Topic/DynamicAnalysisTopicTest
  /Legacy/Messaging/Topic/EmailSentTopicTest
  /Legacy/Messaging/Topic/FixedTopicTest
  /Legacy/Messaging/Topic/MissingTestTopicTest
  /Legacy/Messaging/Topic/TestFailureTopicTest
  /Legacy/Messaging/Topic/TopicDecoratorTest
  image
  consistenttestingday
  subprojectorder
  issuecreation
  configurewarnings
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

add_php_test(projectindb)
set_tests_properties(projectindb PROPERTIES DEPENDS buildgrouprule)

add_php_test(querytests)
set_tests_properties(querytests PROPERTIES DEPENDS projectindb)

add_php_test(testoverview)
set_tests_properties(testoverview PROPERTIES DEPENDS querytests)

add_php_test(buildmodel)
set_tests_properties(buildmodel PROPERTIES DEPENDS testoverview)

add_php_test(multicoverage)
set_tests_properties(multicoverage PROPERTIES DEPENDS buildmodel)

add_php_test(hidecolumns)
set_tests_properties(hidecolumns PROPERTIES DEPENDS multicoverage)

add_php_test(subprojectnextprevious)
set_tests_properties(subprojectnextprevious PROPERTIES DEPENDS hidecolumns)

add_php_test(excludesubprojects)
set_tests_properties(excludesubprojects PROPERTIES DEPENDS subprojectnextprevious)

add_php_test(testhistory)
set_tests_properties(testhistory PROPERTIES DEPENDS subprojectnextprevious)

add_php_test(expectedandmissing)
set_tests_properties(expectedandmissing PROPERTIES DEPENDS testhistory)

add_php_test(crosssubprojectcoverage)
set_tests_properties(crosssubprojectcoverage PROPERTIES DEPENDS expectedandmissing)

add_php_test(aggregatesubprojectcoverage)
set_tests_properties(aggregatesubprojectcoverage PROPERTIES DEPENDS crosssubprojectcoverage)

add_php_test(filtertestlabels)
set_tests_properties(filtertestlabels PROPERTIES DEPENDS aggregatesubprojectcoverage)

add_php_test(viewsubprojects)
set_tests_properties(viewsubprojects PROPERTIES DEPENDS filtertestlabels)

add_php_test(uniquediffs)
set_tests_properties(uniquediffs PROPERTIES DEPENDS viewsubprojects)

add_php_test(testgraphpermissions)
set_tests_properties(testgraphpermissions PROPERTIES DEPENDS uniquediffs)

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

add_php_test(timestatus)
set_tests_properties(timestatus PROPERTIES DEPENDS coveragedirectories)

add_php_test(expiredbuildrules)
set_tests_properties(expiredbuildrules PROPERTIES DEPENDS timestatus)

add_php_test(filterblocks)
set_tests_properties(filterblocks PROPERTIES DEPENDS expiredbuildrules)

add_php_test(indexnextprevious)
set_tests_properties(indexnextprevious PROPERTIES DEPENDS filterblocks)

add_php_test(putdynamicbuilds)
set_tests_properties(putdynamicbuilds PROPERTIES DEPENDS indexnextprevious)

add_php_test(commitauthornotification)
set_tests_properties(commitauthornotification PROPERTIES DEPENDS putdynamicbuilds)

add_php_test(querytestsfilterlabels)
set_tests_properties(querytestsfilterlabels PROPERTIES DEPENDS commitauthornotification)

add_php_test(querytestsrevisionfilter)
set_tests_properties(querytestsrevisionfilter PROPERTIES DEPENDS querytestsfilterlabels)

add_laravel_test(/Feature/SubProjectDependencies)
set_tests_properties(/Feature/SubProjectDependencies PROPERTIES DEPENDS querytestsrevisionfilter)

add_php_test(submitsortingdata)
set_tests_properties(submitsortingdata PROPERTIES DEPENDS /Feature/SubProjectDependencies)

add_php_test(indexfilters)
set_tests_properties(indexfilters PROPERTIES DEPENDS submitsortingdata)

add_php_test(timeline)
set_tests_properties(timeline PROPERTIES DEPENDS indexfilters)

# This test should go last so the removal of builds doesn't clobber other tests
add_php_test(removebuilds)
set_tests_properties(removebuilds PROPERTIES DEPENDS timeline)
