import { createApp } from 'vue';
import { ApolloClient, InMemoryCache } from '@apollo/client/core';
import { createApolloProvider } from '@vue/apollo-option';
import { relayStylePagination } from '@apollo/client/utilities';
import { DefaultApolloClient } from '@vue/apollo-composable';

const components = {
  'build-configure-page': () => import('./components/BuildConfigurePage.vue'),
  'build-notes-page': () => import('./components/BuildNotesPage.vue'),
  'build-summary-page': () => import('./components/BuildSummaryPage.vue'),
  'build-update-page': () => import('./components/BuildUpdatePage.vue'),
  'manage-auth-tokens-page': () => import('./components/ManageAuthTokensPage.vue'),
  'monitor-page': () => import('./components/MonitorPage.vue'),
  'test-id-page': () => import('./components/TestIdPage.vue'),
  'header-nav': () => import('./components/page-header/HeaderNav.vue'),
  'build-dynamic-analysis-page': () => import('./components/BuildDynamicAnalysisPage.vue'),
  'build-dynamic-analysis-id-page': () => import('./components/BuildDynamicAnalysisIdPage.vue'),
  'projects-page': () => import('./components/ProjectsPage.vue'),
  'sub-project-dependencies-page': () => import('./components/SubProjectDependenciesPage.vue'),
  'build-tests-page': () => import('./components/BuildTestsPage.vue'),
  'project-sites-page': () => import('./components/ProjectSitesPage.vue'),
  'sites-id-page': () => import('./components/SitesIdPage.vue'),
  'project-members-page': () => import('./components/ProjectMembersPage.vue'),
  'project-notifications-page': () => import('./components/ProjectNotificationsPage.vue'),
  'users-page': () => import('./components/UsersPage.vue'),
  'build-files-page': () => import('./components/BuildFilesPage.vue'),
  'build-targets-page': () => import('./components/BuildTargetsPage.vue'),
  'build-instrumentation-page': () => import('./components/BuildInstrumentationPage.vue'),
  'build-comments-page': () => import('./components/BuildCommentsPage.vue'),
  'build-build-page': () => import('./components/BuildBuildPage.vue'),
  'coverage-file-page': () => import('./components/CoverageFilePage.vue'),
  'build-coverage-page': () => import('./components/BuildCoveragePage.vue'),
  'create-project-page': () => import('./components/CreateProjectPage.vue'),
  'administration-page': () => import('./components/AdministrationPage.vue'),
  'project-settings-page': () => import('./components/ProjectSettingsPage.vue'),
  'profile-page': () => import('./components/ProfilePage.vue'),
};

const baseURL = document.getElementById('app').getAttribute('data-app-url');

const apolloClient = new ApolloClient({
  cache: new InMemoryCache({
    typePolicies: {
      Query: {
        fields: {
          projects: relayStylePagination(),
          invitations: relayStylePagination(),
          users: relayStylePagination(),
        },
      },
      User: {
        fields: {
          projects: relayStylePagination(),
        },
      },
      Project: {
        fields: {
          sites: relayStylePagination(),
          basicUsers: relayStylePagination(),
          administrators: relayStylePagination(),
          invitations: relayStylePagination(),
          pinnedTestMeasurements: relayStylePagination(),
          repositories: relayStylePagination(),
        },
      },
      Build: {
        fields: {
          tests: relayStylePagination(['filters']),
          labels: relayStylePagination(),
          files: relayStylePagination(),
          urls: relayStylePagination(),
          notes: relayStylePagination(),
          targets: relayStylePagination(),
          errors: relayStylePagination(),
        },
      },
      Site: {
        information: relayStylePagination(),
        maintainers: relayStylePagination(),
      },
    },
  }),
  uri: `${baseURL}/graphql`,
});

const apolloProvider = createApolloProvider({
  defaultClient: apolloClient,
});

document.querySelectorAll('[data-vue-component]').forEach(async (el) => {
  const loadComponent = components[el.dataset.vueComponent];
  if (loadComponent === undefined) {
    throw new Error(`Unknown Vue component: ${el.dataset.vueComponent}`);
  }
  const { default: component } = await loadComponent();

  const app = createApp(component, JSON.parse(el.dataset.vueProps ?? '{}'));
  app.config.globalProperties.$baseURL = baseURL;
  app.use(apolloProvider);
  app.provide(DefaultApolloClient, apolloClient);
  app.mount(el);
});
