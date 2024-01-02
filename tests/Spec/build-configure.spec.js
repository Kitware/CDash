import { mount, config } from '@vue/test-utils';
import axios from 'axios';
import AxiosMockAdapter from 'axios-mock-adapter';
import expect from 'expect';
import BuildConfigure from '../../resources/js/components/BuildConfigure.vue';

config.global.mocks['$baseURL'] = 'http://localhost';
axios.defaults.baseURL = config.global.mocks['$baseURL'];

let apiResponse;
let axiosMockAdapter;

beforeEach(() => {
  axiosMockAdapter = new AxiosMockAdapter(axios);
  config.global.mocks['$axios'] = axios;

  apiResponse = {
    build: {
      site: 'mysite',
      siteid: 1,
      buildname: 'my build',
      buildid: 2,
      buildstarttime: '2023-08-21 12:30:48',
      hassubprojects: 0,
    },
    configures: [{
      status: 0,
      command: 'cmake',
      output: '-- Configuring done\n-- Generating done\n-- Build files have been written to: \/tmp\/bin\/\n',
      configureerrors: 0,
      configurewarnings: 0,
    }],
  };

});

afterEach(() => {
  axiosMockAdapter.restore();
});

test('BuildConfigure handles API response', async () => {
  axiosMockAdapter.onGet('/api/v1/viewConfigure.php?buildid=').reply(200, apiResponse);
  const component = mount(BuildConfigure);
  await new Promise(process.nextTick);
  expect(component.vm.loading).toBe(false);
  const html = component.html();
  expect(html).toContain('mysite');
  expect(html).toContain('my build');
  expect(html).toContain('2023-08-21 12:30:48');
  expect(html).toContain('Configuring done');
  expect(html).toContain('written to: /tmp/bin/');
});
