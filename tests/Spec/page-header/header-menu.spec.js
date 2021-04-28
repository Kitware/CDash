import {mount, config} from "@vue/test-utils";
config.mocks['$baseURL'] = 'http://localhost';
import HeaderMenu from "../../../resources/js/components/page-header/HeaderMenu.vue";
import expect from 'expect';
import moment from "moment";

let component;
const today = moment().format('YYYY-MM-DD');
const bugUrl = 'https://github.com/project/project/issues';
const docUrl = 'https://github.com/project/project/wiki';
const homeUrl = 'https://www.project.tld/';
const vcsUrl = 'https://github.com/project/project';

const indexUrl = `http://localhost/index.php?project=CDash&date=${today}`;
const overviewUrl = `http://localhost/overview.php?project=CDash&date=${today}`;
const buildsUrl = `http://localhost/buildOverview.php?project=CDash&date=${today}`;
const testQueryUrl = `http://localhost/queryTests.php?project=CDash&date=${today}`;
const statisticsUrl = `http://localhost/userStatistics.php?project=CDash&date=${today}`;
const sitesUrl = `http://localhost/viewMap.php?project=CDash&date=${today}`;
const subscribeUrl = 'http://localhost/subscribeProject.php?projectid=111';

const verifyLink = (url, text) => {
  const selector = `a[href='${url}']`;
  const el = component.find(selector);
  expect(el.element.tagName).toBe('A');
  expect(el.text()).toBe(text);
  return el;
};

beforeEach(() => {
  component = mount(HeaderMenu, {
    data () {
      return {
        bugUrl: bugUrl,
        docUrl: docUrl,
        homeUrl: homeUrl,
        vcsUrl: vcsUrl,
        indexUrl: indexUrl,
        overviewUrl: overviewUrl,
        buildsUrl: buildsUrl,
        testQueryUrl: testQueryUrl,
        statisticsUrl: statisticsUrl,
        sitesUrl: sitesUrl,
        subscribeUrl: subscribeUrl,
        hasProject: true,
        showNav: true,
        showSubscribe: true,
      }
    },
    propsData: {
      date: today,
    },
  });
});

test('HeaderMenu has a "Dashboard" link', () => {
  verifyLink(indexUrl, 'Dashboard');
});

test('HeaderMenu has an "Overview" link', () => {
  verifyLink(overviewUrl, 'Overview');
});

test('HeaderMenu has a "Builds" link', () => {
  verifyLink(buildsUrl, 'Builds');
});

test('HeaderMenu has a "Tests Query" link', () => {
  verifyLink(testQueryUrl, 'Tests Query');
});

test('HeaderMenu has a "Statistics" link', () => {
  verifyLink(statisticsUrl, 'Statistics');
});

test('HeaderMenu has a "Sites" link', () => {
  verifyLink(sitesUrl, 'Sites');
});

test('HeaderMenu has a "Project" link', () => {
  const el = component.find('#project_nav');
  expect(el.element.tagName).toBe('A');
  expect(el.text()).toBe('Project');
});

test('HeaderMenu has a "Home" link', () => {
  verifyLink(homeUrl, 'Home');
});

test('HeaderMenu has a "Documentation" link', () => {
  verifyLink(docUrl, 'Documentation');
});

test('HeaderMenu has a "Repository" link', () => {
  verifyLink(vcsUrl, 'Repository');
});

test('HeaderMenu has a "Bug Tracker" link', () => {
  verifyLink(bugUrl, 'Bug Tracker');
});

test('HeaderMenu has a "Subscribe" link', () => {
  verifyLink(subscribeUrl, 'Subscribe');
});
