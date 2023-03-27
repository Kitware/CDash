/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import './bootstrap';
import BuildConfigure from "./components/BuildConfigure";
import BuildNotes from "./components/BuildNotes";
import BuildSummary from "./components/BuildSummary";
import EditProject from "./components/EditProject";
import ManageAuthTokens from "./components/ManageAuthTokens.vue";
import ManageMeasurements from "./components/ManageMeasurements";
import PageHeader from "./components/PageHeader";
import PageFooter from "./components/PageFooter";
import TestDetails from "./components/TestDetails";

const cdash_components = {
  BuildConfigure,
  BuildNotes,
  BuildSummary,
  EditProject,
  ManageAuthTokens,
  ManageMeasurements,
  PageHeader,
  PageFooter,
  TestDetails
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
