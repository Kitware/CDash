/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import './bootstrap';
import BuildConfigure from "./components/BuildConfigure";
import BuildNotes from "./components/BuildNotes";
import BuildSummary from "./components/BuildSummary";
import BuildUpdate from "./components/BuildUpdate";
import EditProject from "./components/EditProject";
import ManageAuthTokens from "./components/ManageAuthTokens.vue";
import ManageMeasurements from "./components/ManageMeasurements";
import Monitor from "./components/Monitor";
import TestDetails from "./components/TestDetails";
import HeaderNav from "./components/page-header/HeaderNav.vue";
import HeaderMenu from "./components/page-header/HeaderMenu.vue";
import HeaderLogo from "./components/page-header/HeaderLogo.vue";

const cdash_components = {
  BuildConfigure,
  BuildNotes,
  BuildSummary,
  BuildUpdate,
  EditProject,
  ManageAuthTokens,
  ManageMeasurements,
  Monitor,
  TestDetails,
  HeaderNav,
  HeaderMenu,
  HeaderLogo
};

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = new Vue({
  el: '#app',
  components: cdash_components,
});
