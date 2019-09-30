import { mount } from 'vue-test-utils';
import HeaderTop from "../../../resources/js/components/page-header/HeaderTop.vue";
import expect from 'expect';

describe('HeaderTop', () => {
  let component;

  beforeEach(() => {
    component = mount(HeaderTop, {
      propsData: {
        user: {},
        pathname: '/api/v1/build/1234',
        menu: {
          home: 'viewProjects.php'
        }
      }
    });
  });

  it ('has an id of #headertop', () => {
    const el = component.find('#headertop');
    expect(el.is('div')).toBe(true);
  });

  it('has child of #headertop with id #topmenu', () => {
      const el = component.find('#headertop');
      expect(el.contains('#topmenu')).toBe(true);
  });

  it('hides the "Login" link if user is logged in', () => {
    const user = {id: 1};
    const link = component.find('a[href="/login"]');
    expect(link.text()).toBe('Login');
    expect(link.classes()).not.toContain('hidden');

    component.setData({user: user});
    expect(link.classes()).toContain('hidden');
  });

  it('hides the "All Dashboards" link if url is not viewProjects.php', () => {
    const link = component.find('a[href="viewProjects.php"]');
    expect(link.classes()).not.toContain('hidden');

    component.setData({
      pathname: 'viewProjects.php',
      menu: {
        home: 'viewProjects.php'
      }
    });

    expect(link.classes()).toContain('hidden');
  });

  it( 'hides the "Register" link if user is logged in', () => {
    const user = {id: 1};
    const link = component.find('a[href="register.php"]');

    expect(link.classes()).not.toContain('hidden');

    component.setData({user: user});

    expect(link.classes()).toContain('hidden');
  });

  it('hides the "My CDash" link if user is not logged in', () => {
    const user = {id: 1};
    const link = component.find('a[href="user.php"]');
    expect(link.text()).toBe('My CDash');

    expect(link.classes()).toContain('hidden');

    component.setData({user: user});

    expect(link.classes()).not.toContain('hidden');
  });

  it('hides the "Log out" link if user is not logged in', () => {
    const user = {id: 1};
    const link = component.find('a[href="/logout"]');
    expect(link.text()).toBe('Log out');

    expect(link.classes()).toContain('hidden');

    component.setData({user: user});

    expect(link.classes()).not.toContain('hidden');
  });
});
