<template>
  <SidebarSection
    title="Resources"
    data-test="resources-section"
  >
    <template
      v-if="canUpdate"
      #actions
    >
      <button
        type="button"
        class="tw-btn tw-btn-ghost tw-btn-xs tw-text-neutral-500"
        title="Edit resources"
        aria-label="Edit resources"
        data-test="edit-resources-button"
        @click="$refs.editDialog.open()"
      >
        <FontAwesomeIcon :icon="FA.faPen" />
      </button>
    </template>

    <ul
      v-if="resources.length > 0"
      class="tw-flex tw-flex-col tw-gap-2"
    >
      <li
        v-for="resource in resources"
        :key="resource.label"
      >
        <a
          :href="resource.url"
          :title="resource.url"
          class="tw-flex tw-items-center tw-gap-2 tw-text-sm hover:tw-underline"
          rel="noopener noreferrer"
          :data-test="resource.testId"
        >
          <FontAwesomeIcon
            :icon="resource.icon"
            class="tw-w-4 tw-shrink-0 tw-text-neutral-500"
          />
          <span class="tw-truncate">{{ resource.label }}</span>
        </a>
      </li>
    </ul>
    <div
      v-else
      class="tw-flex tw-flex-col tw-items-start tw-gap-2 tw-text-sm tw-text-neutral-500"
      data-test="no-resources-message"
    >
      No resources have been added to this project.
      <button
        v-if="canUpdate"
        type="button"
        class="tw-btn tw-btn-sm tw-btn-outline tw-border-base-300 tw-font-normal"
        data-test="add-resources-button"
        @click="$refs.editDialog.open()"
      >
        <FontAwesomeIcon :icon="FA.faPlus" />
        Add resources
      </button>
    </div>

    <EditResourcesDialog
      v-if="canUpdate"
      ref="editDialog"
      :project-id="projectId"
      :vcs-url="vcsUrl"
      :vcs-viewer="vcsViewer"
      :home-url="homeUrl"
      :documentation-url="documentationUrl"
      :bug-tracker-url="bugTrackerUrl"
      :bug-tracker-new-issue-url="bugTrackerNewIssueUrl"
      :license-url="licenseUrl"
      :security-policy-url="securityPolicyUrl"
      :test-data-url="testDataUrl"
    />
  </SidebarSection>
</template>

<script>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
  faBook,
  faBug,
  faCirclePlus,
  faCodeBranch,
  faDatabase,
  faGlobe,
  faPen,
  faPlus,
  faScaleBalanced,
  faShieldHalved,
} from '@fortawesome/free-solid-svg-icons';
import {
  faGithub,
  faGitlab,
} from '@fortawesome/free-brands-svg-icons';
import EditResourcesDialog from './EditResourcesDialog.vue';
import SidebarSection from './SidebarSection.vue';

export default {
  name: 'ResourcesSection',

  components: {
    EditResourcesDialog,
    FontAwesomeIcon,
    SidebarSection,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },

    canUpdate: {
      type: Boolean,
      default: false,
    },

    vcsUrl: {
      type: [String, null],
      default: null,
    },

    // One of GITHUB or GITLAB, or null if unknown.
    vcsViewer: {
      type: [String, null],
      default: null,
    },

    homeUrl: {
      type: [String, null],
      default: null,
    },

    documentationUrl: {
      type: [String, null],
      default: null,
    },

    bugTrackerUrl: {
      type: [String, null],
      default: null,
    },

    bugTrackerNewIssueUrl: {
      type: [String, null],
      default: null,
    },

    licenseUrl: {
      type: [String, null],
      default: null,
    },

    securityPolicyUrl: {
      type: [String, null],
      default: null,
    },

    testDataUrl: {
      type: [String, null],
      default: null,
    },
  },

  computed: {
    FA() {
      return {
        faPen,
        faPlus,
      };
    },

    resources() {
      let repositoryIcon = faCodeBranch;
      if (this.vcsViewer === 'GITHUB') {
        repositoryIcon = faGithub;
      } else if (this.vcsViewer === 'GITLAB') {
        repositoryIcon = faGitlab;
      }

      return [
        { label: 'Repository', url: this.vcsUrl, icon: repositoryIcon, testId: 'repository-link' },
        { label: 'Website', url: this.homeUrl, icon: faGlobe, testId: 'website-link' },
        { label: 'Documentation', url: this.documentationUrl, icon: faBook, testId: 'documentation-link' },
        { label: 'Issue Tracker', url: this.bugTrackerUrl, icon: faBug, testId: 'bug-tracker-link' },
        { label: 'Report an Issue', url: this.bugTrackerNewIssueUrl, icon: faCirclePlus, testId: 'new-issue-link' },
        { label: 'License', url: this.licenseUrl, icon: faScaleBalanced, testId: 'license-link' },
        { label: 'Security Policy', url: this.securityPolicyUrl, icon: faShieldHalved, testId: 'security-policy-link' },
        { label: 'Test Data', url: this.testDataUrl, icon: faDatabase, testId: 'test-data-link' },
      ].filter((resource) => resource.url);
    },
  },
};
</script>
