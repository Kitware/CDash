import {
  createApp,
  defineAsyncComponent,
} from 'vue';
import { ApolloClient, InMemoryCache } from '@apollo/client/core';
import { createApolloProvider } from '@vue/apollo-option';
import { relayStylePagination } from '@apollo/client/utilities';
import { DefaultApolloClient } from '@vue/apollo-composable';

const app = createApp({
  name: 'App',

  components: {
    BuildConfigurePage: defineAsyncComponent(() => import('./components/BuildConfigurePage.vue')),
    BuildNotesPage: defineAsyncComponent(() => import('./components/BuildNotesPage.vue')),
    BuildSummaryPage: defineAsyncComponent(() => import('./components/BuildSummaryPage.vue')),
    BuildUpdatePage: defineAsyncComponent(() => import('./components/BuildUpdatePage.vue')),
    ManageAuthTokensPage: defineAsyncComponent(() => import('./components/ManageAuthTokensPage.vue')),
    MonitorPage: defineAsyncComponent(() => import('./components/MonitorPage.vue')),
    TestIdPage: defineAsyncComponent(() => import('./components/TestIdPage.vue')),
    HeaderNav: defineAsyncComponent(() => import('./components/page-header/HeaderNav.vue')),
    BuildDynamicAnalysisPage: defineAsyncComponent(() => import('./components/BuildDynamicAnalysisPage.vue')),
    BuildDynamicAnalysisIdPage: defineAsyncComponent(() => import('./components/BuildDynamicAnalysisIdPage.vue')),
    ProjectsPage: defineAsyncComponent(() => import('./components/ProjectsPage.vue')),
    SubProjectDependenciesPage: defineAsyncComponent(() => import('./components/SubProjectDependenciesPage.vue')),
    BuildTestsPage: defineAsyncComponent(() => import('./components/BuildTestsPage.vue')),
    ProjectSitesPage: defineAsyncComponent(() => import('./components/ProjectSitesPage.vue')),
    SitesIdPage: defineAsyncComponent(() => import('./components/SitesIdPage.vue')),
    ProjectMembersPage: defineAsyncComponent(() => import('./components/ProjectMembersPage.vue')),
    UsersPage: defineAsyncComponent(() => import('./components/UsersPage.vue')),
    BuildFilesPage: defineAsyncComponent(() => import('./components/BuildFilesPage.vue')),
    BuildTargetsPage: defineAsyncComponent(() => import('./components/BuildTargetsPage.vue')),
    BuildInstrumentationPage: defineAsyncComponent(() => import('./components/BuildInstrumentationPage.vue')),
    BuildCommentsPage: defineAsyncComponent(() => import('./components/BuildCommentsPage.vue')),
    BuildBuildPage: defineAsyncComponent(() => import('./components/BuildBuildPage.vue')),
    CoverageFilePage: defineAsyncComponent(() => import('./components/CoverageFilePage.vue')),
    BuildCoveragePage: defineAsyncComponent(() => import('./components/BuildCoveragePage.vue')),
    CreateProjectPage: defineAsyncComponent(() => import('./components/CreateProjectPage.vue')),
    AdministrationPage: defineAsyncComponent(() => import('./components/AdministrationPage.vue')),
    ProjectSettingsPage: defineAsyncComponent(() => import('./components/ProjectSettingsPage.vue')),
    ProfilePage: defineAsyncComponent(() => import('./components/ProfilePage.vue')),
  },
});

app.config.globalProperties.$baseURL = document.getElementById('app').getAttribute('data-app-url');

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
          tests: relayStylePagination(),
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
  uri: `${app.config.globalProperties.$baseURL}/graphql`,
});

const apolloProvider = createApolloProvider({
  defaultClient: apolloClient,
});

app.use(apolloProvider);
app.provide(DefaultApolloClient, apolloClient);

app.mount('#app');
