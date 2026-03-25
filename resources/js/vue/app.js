import $ from 'jquery';
import * as Vue from 'vue';
import axios from 'axios';
import { ApolloClient, InMemoryCache } from '@apollo/client/core';
import { createApolloProvider } from '@vue/apollo-option';
import VueApolloComponents from '@vue/apollo-components';
import { relayStylePagination } from '@apollo/client/utilities';
import { DefaultApolloClient } from '@vue/apollo-composable';

const app = Vue.createApp({
  components: {
    BuildConfigure: Vue.defineAsyncComponent(() => import('./components/BuildConfigure')),
    BuildNotesPage: Vue.defineAsyncComponent(() => import('./components/BuildNotesPage.vue')),
    BuildSummary: Vue.defineAsyncComponent(() => import('./components/BuildSummary')),
    BuildUpdate: Vue.defineAsyncComponent(() => import('./components/BuildUpdate')),
    UserHomepage: Vue.defineAsyncComponent(() => import('./components/UserHomepage')),
    ManageAuthTokens: Vue.defineAsyncComponent(() => import('./components/ManageAuthTokens.vue')),
    ManageMeasurements: Vue.defineAsyncComponent(() => import('./components/ManageMeasurements')),
    Monitor: Vue.defineAsyncComponent(() => import('./components/Monitor')),
    TestDetails: Vue.defineAsyncComponent(() => import('./components/TestDetails')),
    HeaderNav: Vue.defineAsyncComponent(() => import('./components/page-header/HeaderNav.vue')),
    ViewDynamicAnalysis: Vue.defineAsyncComponent(() => import('./components/ViewDynamicAnalysis.vue')),
    BuildDynamicAnalysisIdPage: Vue.defineAsyncComponent(() => import('./components/BuildDynamicAnalysisIdPage.vue')),
    ProjectsPage: Vue.defineAsyncComponent(() => import('./components/ProjectsPage.vue')),
    SubProjectDependencies: Vue.defineAsyncComponent(() => import('./components/SubProjectDependencies.vue')),
    BuildTestsPage: Vue.defineAsyncComponent(() => import('./components/BuildTestsPage.vue')),
    ProjectSitesPage: Vue.defineAsyncComponent(() => import('./components/ProjectSitesPage.vue')),
    SitesIdPage: Vue.defineAsyncComponent(() => import('./components/SitesIdPage.vue')),
    ProjectMembersPage: Vue.defineAsyncComponent(() => import('./components/ProjectMembersPage.vue')),
    UsersPage: Vue.defineAsyncComponent(() => import('./components/UsersPage.vue')),
    BuildFilesPage: Vue.defineAsyncComponent(() => import('./components/BuildFilesPage.vue')),
    BuildTargetsPage: Vue.defineAsyncComponent(() => import('./components/BuildTargetsPage.vue')),
    BuildCommandsPage: Vue.defineAsyncComponent(() => import('./components/BuildCommandsPage.vue')),
    BuildBuildPage: Vue.defineAsyncComponent(() => import('./components/BuildBuildPage.vue')),
    CoverageFilePage: Vue.defineAsyncComponent(() => import('./components/CoverageFilePage.vue')),
    BuildCoveragePage: Vue.defineAsyncComponent(() => import('./components/BuildCoveragePage.vue')),
    CreateProjectPage: Vue.defineAsyncComponent(() => import('./components/CreateProjectPage.vue')),
    AdministrationPage: Vue.defineAsyncComponent(() => import('./components/AdministrationPage.vue')),
    ProjectSettingsPage: Vue.defineAsyncComponent(() => import('./components/ProjectSettingsPage.vue')),
  },
});

app.config.globalProperties.$baseURL = $('#app').attr('data-app-url');

axios.defaults.baseURL = app.config.globalProperties.$baseURL;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}
else {
  console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

app.config.globalProperties.$axios = axios;

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
      Project: {
        fields: {
          sites: relayStylePagination(),
          basicUsers: relayStylePagination(),
          administrators: relayStylePagination(),
          invitations: relayStylePagination(),
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
app.use(VueApolloComponents);
app.provide(DefaultApolloClient, apolloClient);

window.Vue = Vue;
app.mount('#app');
