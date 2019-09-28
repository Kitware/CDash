import { mount } from 'vue-test-utils';
import HeaderMenu from "../../../resources/js/components/page-header/HeaderMenu.vue";
import expect from 'expect';
import moment from "moment";

describe('HeaderMenu', () => {

  let component;
  const today = moment().format('YYYY-MM-DD');
  const project = 'CDash';
  const homeUrl = 'https://www.project.tld/';
  const vcsUrl = 'https://github.com/project/project';
  const bugUrl = 'https://github.com/project/project/issues';
  const docUrl = 'https://github.com/project/project/wiki';
  const projectId = 111;

  const verifyItHasLink = (url, text) => {
    const selector = `a[href='${url}']`;
    const el = component.find(selector);
    expect(el.is('a')).toBe(true);
    expect(el.text()).toBe(text);
    return el;
  };

  const buildQuery = (query) => {
    const qstrs = [];

    Object.keys(query).forEach((key) => {
      qstrs.push(`${key}=${query[key]}`);
    });

    return qstrs.join('&');
  };

  beforeEach(() => {
    component = mount(HeaderMenu, {
        propsData: {
          project: project,
          homeUrl: homeUrl,
          vcsUrl: vcsUrl,
          bugUrl: bugUrl,
          docUrl: docUrl,
          today: today,
          projectId: projectId
        }
    });
  });

  it('has a "Dashboard" link', () => {
    const qstr = buildQuery({project: project, date: today});
    verifyItHasLink(`index.php?${qstr}`, 'Dashboard');
  });

  it('has an "Overview" link', () => {
    const qstr = buildQuery({project: project, date: today});
    verifyItHasLink(`overview.php?${qstr}`, 'Overview');
  });

  it('has a "Tests" link', () => {
    const qstr = buildQuery({project: project, date: today});
    verifyItHasLink(`buildOverview.php?${qstr}`, 'Builds');
  });

  it('has a "Test Query" link', () => {
    const qstr = buildQuery({project: project, date: today});
    verifyItHasLink(`queryTests.php?${qstr}`, 'Test Query');
  });

  it('has a "Statistics" link', () => {
    const qstr = buildQuery({project: project, date: today});
    verifyItHasLink(`userStatistics.php?${qstr}`, 'Statistics');
  });

  it('has a "Sites" link', () => {
    const qstr = buildQuery({project: project, date: today});
    verifyItHasLink(`viewMap.php?${qstr}`, 'Sites');
  });

  it('has a "Calendar" link', () => {
    const el = component.find('a[href="#"]');
    expect(el.is('a')).toBe(true);
    // need a test for the onclick handler

    // check that Calendar has no child menu
    const sibling = component.find("a[href='#'] + ul");
    expect(sibling.exists()).toBe(false);
  });

  it('has a "Project" link', () => {
    const el = component.find(`a[href="index.php?project=${project}"]`);
    expect(el.is('a')).toBe(true);
    expect(el.text()).toBe('Project');
  });

  it('has a "Home" link', () => {
    const el = component.find(`a[href="${homeUrl}"]`);
    expect(el.is('a')).toBe(true);
    expect(el.text()).toBe('Home');
  });

  it('has a "Documentation" link', () => {
    const el = component.find(`a[href="${docUrl}"]`);
    expect(el.is('a')).toBe(true);
    expect(el.text()).toBe('Documentation');
  });

  it('has a "Repository" link', () => {
    const el = component.find(`a[href="${vcsUrl}"]`);
    expect(el.is('a')).toBe(true);
    expect(el.text()).toBe('Repository');
  });

  it('has a "Bug Tracker" link', () => {
    const el = component.find(`a[href="${bugUrl}"]`);
    expect(el.is('a')).toBe(true);
    expect(el.text()).toBe('Bug Tracker');
  });

  it('has a "Subscribe" link', () => {
    const el = component.find(`a[href="subscribeProject.php?projectid=${projectId}"]`);
    expect(el.is('a')).toBe(true);
    expect(el.text()).toBe('Subscribe');
  });
});
