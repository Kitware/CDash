/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import * as Vue from 'vue';
import axios from 'axios';

import BuildConfigure from './components/BuildConfigure';
import BuildNotes from './components/BuildNotes';
import BuildSummary from './components/BuildSummary';
import BuildUpdate from './components/BuildUpdate';
import EditProject from './components/EditProject';
import UserHomepage from './components/UserHomepage';
import ManageAuthTokens from './components/ManageAuthTokens.vue';
import ManageMeasurements from './components/ManageMeasurements';
import Monitor from './components/Monitor';
import TestDetails from './components/TestDetails';
import HeaderNav from './components/page-header/HeaderNav.vue';
import HeaderMenu from './components/page-header/HeaderMenu.vue';
import HeaderLogo from './components/page-header/HeaderLogo.vue';
import ViewDynamicAnalysis from './components/ViewDynamicAnalysis.vue';
import AllProjects from './components/AllProjects.vue';
import SubProjectDependencies from './components/SubProjectDependencies.vue';

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { library } from '@fortawesome/fontawesome-svg-core';
import { fas } from '@fortawesome/free-solid-svg-icons';
import { far } from '@fortawesome/free-regular-svg-icons';
import { fab } from '@fortawesome/free-brands-svg-icons';

library.add(fas, far, fab);

const cdash_components = {
  FontAwesomeIcon,
  BuildConfigure,
  BuildNotes,
  BuildSummary,
  BuildUpdate,
  EditProject,
  UserHomepage,
  ManageAuthTokens,
  ManageMeasurements,
  Monitor,
  TestDetails,
  HeaderNav,
  HeaderMenu,
  HeaderLogo,
  ViewDynamicAnalysis,
  AllProjects,
  SubProjectDependencies,
};

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = Vue.createApp({
  components:  cdash_components,
});

app.config.globalProperties.$baseURL = process.env.MIX_APP_URL;

axios.defaults.baseURL = process.env.MIX_APP_URL;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}
else {
  console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

app.config.globalProperties.$axios = axios;

window.Vue = Vue;
app.mount('#app');
