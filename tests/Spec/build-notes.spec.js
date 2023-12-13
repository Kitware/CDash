import { mount, config } from '@vue/test-utils';
import axios from 'axios';
import AxiosMockAdapter from 'axios-mock-adapter';
import expect from 'expect';
import BuildNotes from '../../resources/js/components/BuildNotes.vue';

config.global.mocks['$baseURL'] = 'http://localhost';
axios.defaults.baseURL = config.global.mocks['$baseURL'];

import $ from 'jquery';
$.plot = function() {
  return null;
};
global.$ = $;

let apiResponse;
let axiosMockAdapter;

beforeEach(() => {
  axiosMockAdapter = new AxiosMockAdapter(axios);
  config.global.mocks['$axios'] = axios;
  apiResponse = {
    build: {
      buildid: 1,
      buildname: 'my build',
      siteid: 1,
      site: 'mysite',
      starttime: '5 minutes ago',
    },
    notes: [
      {
        name: 'a note',
        text: 'note text',
        time: 'yesterday',
      },
    ],
    user: {
      id: 1,
    },
  };
});

afterEach(() => {
  axiosMockAdapter.restore();
});

test('BuildNote handles API response', async () => {
  axiosMockAdapter.onGet('/api/v1/viewNotes.php?buildid=').reply(200, apiResponse);
  const component = mount(BuildNotes);
  await new Promise(process.nextTick);
  expect(component.vm.loading).toBe(false);
  expect(component.vm.cdash.notes.length).toBe(1);

  // Verify some expected content.
  const html = component.html();
  expect(html).toContain('my build');
  expect(html).toContain('mysite');
  expect(html).toContain('5 minutes ago');

  // Since we only have one note it should be displayed by default.
  expect(component.find('#notetext0').isVisible()).toBe(true);
});

test('BuildNote can toggle notes', async () => {
  apiResponse.notes.push({
    name: 'another note',
    text: 'more note text',
    time: 'later on',
  });
  axiosMockAdapter.onGet('/api/v1/viewNotes.php?buildid=').reply(200, apiResponse);
  const component = mount(BuildNotes);
  await new Promise(process.nextTick);

  // Two notes, both hidden by default.
  expect(component.vm.cdash.notes.length).toBe(2);
  expect(component.find('#notetext0').isVisible()).toBe(false);
  expect(component.find('#notetext1').isVisible()).toBe(false);

  // Toggle a note back on.
  const note_button = component.find('#note0');
  note_button.trigger('click');
  await new Promise(process.nextTick);
  expect(component.find('#notetext0').isVisible()).toBe(true);
});
