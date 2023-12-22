import {mount, config, createLocalVue} from '@vue/test-utils';
import axios from 'axios';
import AxiosMockAdapter from 'axios-mock-adapter';
import bootstrap from 'bootstrap';
import expect from 'expect';
import ManageMeasurements from '../../resources/js/components/ManageMeasurements.vue';

config.global.mocks['$baseURL'] = '';
axios.defaults.baseURL = config.global.mocks['$baseURL'];

import $ from 'jquery';
global.$ = $;

let axiosMockAdapter;
let apiResponse;

beforeEach(() => {
  axiosMockAdapter = new AxiosMockAdapter(axios);
  config.global.mocks['$axios'] = axios;
  apiResponse = {
    measurements: [
      {
        id: 1,
        name: 'my measurement',
        position: 1,
      },
    ],
    projectid: 1,
    user: {
      admin: 1,
      id: 1,
    },
  };
});

afterEach(() => {
  axiosMockAdapter.restore();
});

test('ManageMeasurements handles API response', async () => {
  axiosMockAdapter.onGet('/api/v1/manageMeasurements.php?projectid=').reply(200, apiResponse);
  const component = mount(ManageMeasurements);
  await new Promise(process.nextTick);
  expect(component.vm.loading).toBe(false);
  expect(component.vm.cdash.measurements.length).toBe(1);
});

test('ManageMeasurements can add a measurement', async () => {
  axiosMockAdapter.onGet('/api/v1/manageMeasurements.php?projectid=').reply(200, apiResponse);
  const component = mount(ManageMeasurements);
  await new Promise(process.nextTick);

  const api_response = {
    id: 2,
  };
  axiosMockAdapter.onPost('/api/v1/manageMeasurements.php').reply(200, api_response);

  const new_measurement_input = component.find('#newMeasurement');
  new_measurement_input.element.value = 'my new measurement';
  new_measurement_input.trigger('input');
  await new Promise(process.nextTick);
  expect(component.vm.newMeasurementName).toBe('my new measurement');

  const save_button = component.find('#submit_button');
  save_button.trigger('click');
  await new Promise(process.nextTick);

  expect(component.vm.cdash.measurements.length).toBe(2);
  expect(component.vm.cdash.measurements[1].name).toBe('my new measurement');
});

test('ManageMeasurements can delete a measurement', async () => {
  axiosMockAdapter.onGet('/api/v1/manageMeasurements.php?projectid=').reply(200, apiResponse);
  const component = mount(ManageMeasurements);
  await new Promise(process.nextTick);

  // Click trash can icon.
  const delete_measurement_span = component.find('.glyphicon-trash');
  delete_measurement_span.trigger('click');
  await new Promise(process.nextTick);
  expect(component.vm.measurementToDelete).toBe(1);

  // Click confirmation button.
  const api_response = {
    id: 1,
  };
  axiosMockAdapter.onDelete('/api/v1/manageMeasurements.php').reply(200, api_response);
  const delete_measurement_button = component.find('#confirmDeleteMeasurementButton');
  delete_measurement_button.trigger('click');
  await new Promise(process.nextTick);
  expect(component.vm.cdash.measurements.length).toBe(0);
});
