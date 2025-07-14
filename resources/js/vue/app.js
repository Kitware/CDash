/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import * as Vue from 'vue';

import axios from 'axios';

import { ApolloClient, InMemoryCache } from '@apollo/client/core';
import { createApolloProvider } from '@vue/apollo-option';
import VueApolloComponents from '@vue/apollo-components';
import { relayStylePagination } from '@apollo/client/utilities';
import { DefaultApolloClient } from '@vue/apollo-composable';

const BuildConfigure = Vue.defineAsyncComponent(() => import('./components/BuildConfigure'));
const BuildNotesPage = Vue.defineAsyncComponent(() => import('./components/BuildNotesPage.vue'));
const BuildSummary = Vue.defineAsyncComponent(() => import('./components/BuildSummary'));
const BuildUpdate = Vue.defineAsyncComponent(() => import('./components/BuildUpdate'));
const EditProject = Vue.defineAsyncComponent(() => import('./components/EditProject'));
const UserHomepage = Vue.defineAsyncComponent(() => import('./components/UserHomepage'));
const ManageAuthTokens = Vue.defineAsyncComponent(() => import('./components/ManageAuthTokens.vue'));
const ManageMeasurements = Vue.defineAsyncComponent(() => import('./components/ManageMeasurements'));
const Monitor = Vue.defineAsyncComponent(() => import('./components/Monitor'));
const TestDetails = Vue.defineAsyncComponent(() => import('./components/TestDetails'));
const HeaderNav = Vue.defineAsyncComponent(() => import('./components/page-header/HeaderNav.vue'));
const ViewDynamicAnalysis = Vue.defineAsyncComponent(() => import('./components/ViewDynamicAnalysis.vue'));
const AllProjects = Vue.defineAsyncComponent(() => import('./components/AllProjects.vue'));
const SubProjectDependencies = Vue.defineAsyncComponent(() => import('./components/SubProjectDependencies.vue'));
const BuildTestsPage = Vue.defineAsyncComponent(() => import('./components/BuildTestsPage.vue'));
const ProjectSitesPage = Vue.defineAsyncComponent(() => import('./components/ProjectSitesPage.vue'));
const SitesIdPage = Vue.defineAsyncComponent(() => import('./components/SitesIdPage.vue'));
const ProjectMembersPage = Vue.defineAsyncComponent(() => import('./components/ProjectMembersPage.vue'));
const UsersPage = Vue.defineAsyncComponent(() => import('./components/UsersPage.vue'));
const BuildFilesPage = Vue.defineAsyncComponent(() => import('./components/BuildFilesPage.vue'));

const cdash_components = {
  BuildConfigure,
  BuildNotesPage,
  BuildSummary,
  BuildUpdate,
  EditProject,
  UserHomepage,
  ManageAuthTokens,
  ManageMeasurements,
  Monitor,
  TestDetails,
  HeaderNav,
  ViewDynamicAnalysis,
  AllProjects,
  SubProjectDependencies,
  BuildTestsPage,
  ProjectSitesPage,
  SitesIdPage,
  ProjectMembersPage,
  UsersPage,
  BuildFilesPage,
};

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = Vue.createApp({
  components:  cdash_components,
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
