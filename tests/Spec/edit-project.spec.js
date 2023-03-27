import {mount, config, createLocalVue} from "@vue/test-utils";
import axios from 'axios'
import AxiosMockAdapter from 'axios-mock-adapter';
import BootstrapVue from 'bootstrap-vue';
import expect from 'expect';
import EditProject from "../../resources/js/components/EditProject.vue";

const localVue = createLocalVue();
localVue.use(BootstrapVue);

config.mocks['$baseURL'] = '';
axios.defaults.baseURL = config.mocks['$baseURL'];

import $ from 'jquery'
global.$ = $

let axiosMockAdapter;
let newResponse;
let editResponse;

beforeEach(function() {
  axiosMockAdapter = new AxiosMockAdapter(axios);
  config.mocks['$axios'] = axios;
  newResponse = {
    edit: 0,
    noproject: 1,
    project: {
      AuthenticateSubmissions: false,
      AutoremoveMaxBuilds: 500,
      AutoremoveTimeframe: 60,
      CoverageThreshold: 70,
      EmailBrokenSubmission: 1,
      EmailMaxChars: 255,
      EmailMaxItems: 5,
      ErrorsFilter: "",
      NightlyTime: "01:00:00 UTC",
      Public: 0,
      ShowCoverageCode: 1,
      TestTimeMaxStatus: 3,
      TestTimeStd: 4,
      TestTimeStdThreshold: 1,
      UploadQuota: 1,
      WarningsFilter: "",
      repositories: [],
    },
    user: {
      admin: 1,
      id: 1,
    },
  };

  editResponse = {
    edit: 1,
    logoid: 0,
    project: {
      AuthenticateSubmissions: 0,
      AutoremoveMaxBuilds: 500,
      AutoremoveTimeframe: 60,
      CoverageThreshold: 70,
      CvsViewerType: "github",
      Description: "my project desc",
      DisplayLabels: 0,
      EmailBrokenSubmission: 1,
      EmailMaxChars: 255,
      EmailMaxItems: 5,
      ErrorsFilter: "",
      Filled: true,
      Id: 1,
      ImageId: 0,
      MaxUploadQuota: 10,
      Name: "MyTestingProject",
      NightlyTime: "01:00:00 UTC",
      Public: 1,
      ShareLabelFilters: 0,
      ShowCoverageCode: 1,
      ShowIPAddresses: 0,
      ShowTestTime: 0,
      TestTimeMaxStatus: 3,
      TestTimeStd: 4,
      TestTimeStdThreshold: 1,
      UploadQuota: 1,
      ViewSubProjectsLink: 1,
      WarningsFilter: "",
      blockedbuilds: [],
      name_encoded: "MyTestingProject",
      repositories: [],
    },
    user: {
      admin: 1,
      id: 1,
    },
  };
});

afterEach(function() {
  axiosMockAdapter.restore();
});

test('EditProject handles new project API response', async () => {
  axiosMockAdapter.onGet('/api/v1/createProject.php').reply(200, newResponse);
  const component = mount(EditProject, { localVue } );
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  expect(component.vm.loading).toBe(false);
  expect(component.vm.selectedProject).toBe(0);
  expect(component.vm.cdash.tabs.Logo.disabled).toBe(true);
  expect(component.vm.cdash.submitdisabled).toBe(true);
});

test('EditProject handles edit project API response', async () => {
  axiosMockAdapter.onGet('/api/v1/createProject.php?projectid=999999999').reply(200, editResponse);
  const component = mount(EditProject, { localVue, propsData: { projectid: 999999999 } } );
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  expect(component.vm.loading).toBe(false);
  expect(component.vm.selectedProject).toBe(999999999);
  expect(component.vm.cdash.tabs.Logo.disabled).toBe(false);
});
