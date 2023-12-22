import {mount, config, createWrapper} from '@vue/test-utils';
config.global.mocks['$baseURL'] = 'http://localhost';
import HeaderMenu from '../../../resources/js/components/page-header/HeaderMenu.vue';

import expect from 'expect';
import moment from 'moment';

let component;
const today = moment().format('YYYY-MM-DD');
const bugUrl = 'https://github.com/project/project/issues';
const docUrl = 'https://github.com/project/project/wiki';
const homeUrl = 'https://www.project.tld/';
const vcsUrl = 'https://github.com/project/project';

const verifyLink = (url, text) => {
  const selector = `a[href='${url}']`;
  const el = component.find(selector);
  expect(el.element.tagName).toBe('A');
  expect(el.text()).toBe(text);
  return el;
};

beforeEach(() => {
  component = mount(HeaderMenu);
  const apiData = {
    'bugtracker': bugUrl,
    'date': today,
    'documentation': docUrl,
    'home': homeUrl,
    'menu': {},
    'projectid': 111,
    'projectname_encoded': 'TestProject',
    'querytestfilters': '&filtercount=1&showfilters=1&field1=status&compare1=62&value1=Passed',
    'user': {'id': null},
    'vcs': vcsUrl,
  };
  component.vm.handleApiResponse(apiData);
});

test('HeaderMenu has a "Dashboard" link', () => {
  const expected = `http://localhost/index.php?project=TestProject&date=${today}`;
  expect(component.vm.indexUrl).toBe(expected);
  verifyLink(expected, 'Dashboard');
});

test('HeaderMenu has an "Overview" link', () => {
  const expected = `http://localhost/overview.php?project=TestProject&date=${today}`;
  expect(component.vm.overviewUrl).toBe(expected);
  verifyLink(expected, 'Overview');
});

test('HeaderMenu has a "Builds" link', () => {
  const expected = `http://localhost/buildOverview.php?project=TestProject&date=${today}`;
  expect(component.vm.buildsUrl).toBe(expected);
  verifyLink(expected, 'Builds');
});

test('HeaderMenu has a "Tests Query" link', () => {
  const expected = `http://localhost/queryTests.php?project=TestProject&date=${today}&filtercount=1&showfilters=1&field1=status&compare1=62&value1=Passed`;
  expect(component.vm.testQueryUrl).toBe(expected);
  verifyLink(expected, 'Tests Query');
});

test('HeaderMenu has a "Statistics" link', () => {
  const expected = `http://localhost/userStatistics.php?project=TestProject&date=${today}`;
  expect(component.vm.statisticsUrl).toBe(expected);
  verifyLink(expected, 'Statistics');
});

test('HeaderMenu has a "Sites" link', () => {
  const expected = `http://localhost/viewMap.php?project=TestProject&date=${today}`;
  expect(component.vm.sitesUrl).toBe(expected);
  verifyLink(expected, 'Sites');
});

test('HeaderMenu has a "Project" link', () => {
  const el = component.find('#project_nav');
  expect(el.element.tagName).toBe('A');
  expect(el.text()).toBe('Project');
});

test('HeaderMenu has a "Home" link', () => {
  expect(component.vm.homeUrl).toBe(homeUrl);
  verifyLink(homeUrl, 'Home');
});

test('HeaderMenu has a "Documentation" link', () => {
  expect(component.vm.docUrl).toBe(docUrl);
  verifyLink(docUrl, 'Documentation');
});

test('HeaderMenu has a "Repository" link', () => {
  expect(component.vm.vcsUrl).toBe(vcsUrl);
  verifyLink(vcsUrl, 'Repository');
});

test('HeaderMenu has a "Bug Tracker" link', () => {
  expect(component.vm.bugUrl).toBe(bugUrl);
  verifyLink(bugUrl, 'Bug Tracker');
});

test('HeaderMenu has a "Subscribe" link', () => {
  const expected = 'http://localhost/subscribeProject.php?projectid=111';
  expect(component.vm.subscribeUrl).toBe(expected);
  verifyLink(component.vm.subscribeUrl, 'Subscribe');
});
