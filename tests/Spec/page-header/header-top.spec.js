import {mount, config} from "@vue/test-utils";
config.mocks['$baseURL'] = 'http://localhost';
import HeaderTop from "../../../resources/js/components/page-header/HeaderTop.vue";
import expect from 'expect';

let component;

const uri = {
  home: 'http://localhost/viewProjects.php',
  login: 'http://localhost/login',
  logout: 'http://localhost/logout',
  register: 'http://localhost/register',
  profile: 'http://localhost/user.php',
};

beforeEach(() => {
  component = mount(HeaderTop, {
    propsData: {
      user: {},
      uri: uri,
    }
  });
});

it ('HeaderTop has an id of #headertop', () => {
  const el = component.find('#headertop');
  expect(el.element.tagName).toBe('DIV');
});

it('HeaderTop has child of #headertop with id #topmenu', () => {
    const el = component.find('#headertop');
    expect(el.find('#topmenu').exists()).toBe(true);
});

it('HeaderTop has a "Login" link', () => {
  const el = component.find('#cdash-login-link');
  expect(el.attributes('href')).toBe(uri.login);
});

it('HeaderTop has a "Register" link', () => {
  const el = component.find('#cdash-register-link');
  expect(el.attributes('href')).toBe(uri.register);
});

it('HeaderTop has a "My CDash" link', () => {
  const el = component.find('#cdash-profile-link');
  expect(el.attributes('href')).toBe(uri.profile);
});

it('HeaderTop has a "Logout" link', () => {
  const el = component.find('#cdash-logout-link');
  expect(el.attributes('href')).toBe(uri.logout);
});

it('HeaderTop has an "All Dashboards" link', () => {
  const el = component.find('#cdash-home-link');
  expect(el.attributes('href')).toBe(uri.home);
})
