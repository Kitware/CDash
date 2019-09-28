import { mount } from 'vue-test-utils';
import HeaderNav from "../../resources/js/components/page-header/HeaderNav.vue";
import expect from 'expect';

describe('HeaderNav', () => {
  let component;

  const testButtonState = (button) => {
    const selector = `#header-nav-${button}-btn`;
    const el = component.find(selector);
    expect(el.classes()).toContain('btn-disabled');

    component.setProps({[button]: '/some/endpoint'});
    expect(el.classes()).not.toContain('btn-disabled');
    expect(el.classes()).toContain('btn');
  };

  beforeEach(() => {
    component = mount(HeaderNav);
  });

  it('has a "Prev" button that is disabled when no previous href property exists', () => {
    testButtonState('previous');
  });

  it('has a "Current" button that is disabled when no current href property exists', () => {
    testButtonState('current');
  });

  it('has a "Next" button that is disabled when no next url property exists', () => {
    testButtonState('next');
  });
});
