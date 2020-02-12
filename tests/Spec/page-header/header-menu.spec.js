import {mount, config} from "@vue/test-utils";
config.mocks['$baseURL'] = 'http://localhost';
import HeaderMenu from "../../../resources/js/components/page-header/HeaderMenu.vue";
import expect from 'expect';
import moment from "moment";

describe('HeaderMenu', () => {

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

  const verifyItHasLink = (url, text) => {
    const selector = `a[href='${url}']`;
    const el = component.find(selector);
    expect(el.is('a')).toBe(true);
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
          showNav: true,
          showSubscribe: true,
        }
      },
      propsData: {
        date: today,
      },
    });
  });

  it('has a "Dashboard" link', () => {
    verifyItHasLink(indexUrl, 'Dashboard');
  });

  it('has an "Overview" link', () => {
    verifyItHasLink(overviewUrl, 'Overview');
  });

  it('has a "Builds" link', () => {
    verifyItHasLink(buildsUrl, 'Builds');
  });

  it('has a "Tests Query" link', () => {
    verifyItHasLink(testQueryUrl, 'Tests Query');
  });

  it('has a "Statistics" link', () => {
    verifyItHasLink(statisticsUrl, 'Statistics');
  });

  it('has a "Sites" link', () => {
    verifyItHasLink(sitesUrl, 'Sites');
  });

  it('has a "Project" link', () => {
    const el = component.find('#project_nav');
    expect(el.is('a')).toBe(true);
    expect(el.text()).toBe('Project');
  });

  it('has a "Home" link', () => {
    verifyItHasLink(homeUrl, 'Home');
  });

  it('has a "Documentation" link', () => {
    verifyItHasLink(docUrl, 'Documentation');
  });

  it('has a "Repository" link', () => {
    verifyItHasLink(vcsUrl, 'Repository');
  });

  it('has a "Bug Tracker" link', () => {
    verifyItHasLink(bugUrl, 'Bug Tracker');
  });

  it('has a "Subscribe" link', () => {
    verifyItHasLink(subscribeUrl, 'Subscribe');
  });
});
