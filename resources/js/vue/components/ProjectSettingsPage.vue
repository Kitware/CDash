<template>
  <div class="tw-flex tw-flex-row tw-w-full tw-justify-center">
    <div class="tw-w-full tw-flex tw-flex-col tw-gap-4">
      <div
        v-if="fatalError"
        role="alert"
        class="tw-alert tw-alert-error"
      >
        <font-awesome-icon
          :icon="FA.faCircleXmark"
          class="tw-h-6 tw-w-6"
        />
        <span>{{ fatalError }}</span>
      </div>

      <div class="tw-flex tw-flex-row tw-gap-8 tw-items-start">
        <ul class="tw-menu tw-bg-base-200 tw-rounded-box tw-w-56">
          <li>
            <a
              :class="{'tw-active': currentSection === 'details'}"
              @click="currentSection = 'details'"
            >General</a>
          </li>
          <li>
            <a
              :class="{'tw-active': currentSection === 'access'}"
              @click="currentSection = 'access'"
            >Access Control</a>
          </li>
          <li>
            <a
              :class="{'tw-active': currentSection === 'trackers'}"
              @click="currentSection = 'trackers'"
            >Integrations</a>
          </li>
          <li>
            <a
              :class="{'tw-active': currentSection === 'notifications'}"
              @click="currentSection = 'notifications'"
            >Notifications</a>
          </li>
          <li>
            <a
              :class="{'tw-active': currentSection === 'coverage'}"
              @click="currentSection = 'coverage'"
            >Coverage</a>
          </li>
          <li>
            <a
              :class="{'tw-active': currentSection === 'tests'}"
              @click="currentSection = 'tests'"
            >Tests</a>
          </li>
        </ul>

        <div class="tw-flex-grow">
          <div v-show="currentSection === 'details'">
            <h2 class="tw-text-2xl tw-font-bold">
              General
            </h2>
            <div class="tw-divider" />
            <div class="tw-flex tw-items-center tw-gap-4">
              <project-logo
                v-if="project"
                :project-name="form.name"
                :image-url="project.logoUrl"
                class="tw-w-16 tw-h-16"
              />
              <form
                :action="`/projects/${projectId}/logo`"
                method="post"
                enctype="multipart/form-data"
                class="tw-flex-grow tw-flex tw-items-center tw-gap-2"
              >
                <input
                  type="hidden"
                  name="_token"
                  :value="csrfToken"
                >
                <input
                  type="file"
                  name="logo"
                  class="tw-file-input tw-file-input-bordered tw-w-full"
                >
                <button
                  type="submit"
                  class="tw-btn tw-btn-primary"
                >
                  Upload
                </button>
              </form>
            </div>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Project Name
              </span>
              <input
                v-model="form.name"
                type="text"
                class="tw-input tw-input-bordered tw-w-full"
                :class="{'tw-input-error': validationErrors.name}"
              >
              <span
                v-if="validationErrors.name"
                class="tw-label tw-text-error"
              >
                {{ validationErrors.name[0] }}
              </span>
            </label>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Description
              </span>
              <textarea
                v-model="form.description"
                class="tw-textarea tw-textarea-bordered tw-h-24 tw-w-full"
                :class="{'tw-textarea-error': validationErrors.description}"
              />
              <span
                v-if="validationErrors.description"
                class="tw-label tw-text-error"
              >
                {{ validationErrors.description[0] }}
              </span>
            </label>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Home URL
              </span>
              <input
                v-model="form.homeUrl"
                type="text"
                class="tw-input tw-input-bordered tw-w-full"
                placeholder="https://example.com"
              >
            </label>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Documentation URL
              </span>
              <input
                v-model="form.documentationUrl"
                type="text"
                class="tw-input tw-input-bordered tw-w-full"
                placeholder="https://example.com"
              >
            </label>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Test Data URL
              </span>
              <input
                v-model="form.testDataUrl"
                type="text"
                class="tw-input tw-input-bordered tw-w-full"
                placeholder="https://example.com"
              >
            </label>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Nightly Time
              </span>
              <input
                v-model="form.nightlyTime"
                type="text"
                class="tw-input tw-input-bordered tw-w-full"
              >
            </label>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Build Retention (days)
              </span>
              <input
                v-model="form.autoRemoveTimeFrame"
                type="number"
                class="tw-input tw-input-bordered tw-w-full"
              >
            </label>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Maximum Builds Removed Per Day
              </span>
              <input
                v-model="form.autoRemoveMaxBuilds"
                type="number"
                class="tw-input tw-input-bordered tw-w-full"
              >
            </label>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                File Upload Limit (GB)
              </span>
              <input
                v-model="form.fileUploadLimit"
                type="number"
                class="tw-input tw-input-bordered tw-w-full"
              >
            </label>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Banner Message
              </span>
              <textarea
                v-model="form.banner"
                class="tw-textarea tw-textarea-bordered tw-h-24 tw-w-full"
              />
            </label>
            <div class="tw-form-control">
              <label class="tw-cursor-pointer tw-label tw-justify-start tw-gap-2">
                <input
                  v-model="form.displayLabels"
                  type="checkbox"
                  class="tw-toggle"
                >
                <span class="tw-label-text">Display Labels</span>
              </label>
            </div>
            <div class="tw-form-control">
              <label class="tw-cursor-pointer tw-label tw-justify-start tw-gap-2">
                <input
                  v-model="form.showViewSubProjectsLink"
                  type="checkbox"
                  class="tw-toggle"
                >
                <span class="tw-label-text">Show View SubProjects Link</span>
              </label>
            </div>
          </div>

          <div v-show="currentSection === 'access'">
            <h2 class="tw-text-2xl tw-font-bold">
              Access Control
            </h2>
            <div class="tw-divider" />
            <div class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Visibility
              </span>
              <div class="tw-w-full tw-flex tw-flex-col tw-gap-1">
                <label class="tw-flex tw-items-center tw-gap-2">
                  <input
                    v-model="form.visibility"
                    type="radio"
                    name="visibility"
                    class="tw-radio"
                    value="PUBLIC"
                  >
                  <div>
                    <span class="tw-label-text">
                      <font-awesome-icon :icon="FA.faEarthAmericas" /> Public
                    </span>
                    <div class="tw-text-xs tw-text-neutral-500">
                      Does not require authentication to access.
                    </div>
                  </div>
                </label>
                <label class="tw-flex tw-items-center tw-gap-2">
                  <input
                    v-model="form.visibility"
                    type="radio"
                    name="visibility"
                    class="tw-radio"
                    value="PROTECTED"
                  >
                  <div>
                    <span class="tw-label-text">
                      <font-awesome-icon :icon="FA.faShieldHalved" /> Protected
                    </span>
                    <div class="tw-text-xs tw-text-neutral-500">
                      Access limited to authenticated users.
                    </div>
                  </div>
                </label>
                <label class="tw-flex tw-items-center tw-gap-2">
                  <input
                    v-model="form.visibility"
                    type="radio"
                    name="visibility"
                    class="tw-radio"
                    value="PRIVATE"
                  >
                  <div>
                    <span class="tw-label-text">
                      <font-awesome-icon :icon="FA.faLock" /> Private
                    </span>
                    <div class="tw-text-xs tw-text-neutral-500">
                      Requires access to be granted explicitly.
                    </div>
                  </div>
                </label>
              </div>
            </div>
            <div class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Authenticated Submissions
              </span>
              <label class="tw-cursor-pointer tw-label tw-justify-start tw-gap-2">
                <input
                  v-model="form.authenticateSubmissions"
                  type="checkbox"
                  class="tw-toggle"
                >
                <span class="tw-label-text">Require submissions to provide a valid authentication token.</span>
              </label>
            </div>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                LDAP Filter
              </span>
              <div class="tw-text-xs tw-text-neutral-500">
                A LDAP group users must be a member of to access the project. Requires LDAP to be configured.
              </div>
              <input
                v-model="form.ldapFilter"
                type="text"
                class="tw-input tw-input-bordered tw-w-full"
                :disabled="!ldapEnabled"
              >
            </label>
          </div>

          <div v-show="currentSection === 'trackers'">
            <h2 class="tw-text-2xl tw-font-bold">
              Integrations
            </h2>
            <div class="tw-divider" />
            <h3 class="tw-text-xl tw-font-bold">
              Repository
            </h3>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Repository Type
              </span>
              <select
                v-model="form.vcsViewer"
                class="tw-select tw-select-bordered"
              >
                <option :value="null">
                  None
                </option>
                <option value="GITHUB">
                  GitHub
                </option>
                <option value="GITLAB">
                  GitLab
                </option>
              </select>
            </label>
            <label
              v-show="form.vcsViewer"
              class="tw-form-control tw-w-full"
            >
              <span class="tw-label tw-label-text tw-font-bold">
                Repository URL
              </span>
              <input
                v-model="form.vcsUrl"
                type="text"
                class="tw-input tw-input-bordered tw-w-full"
                placeholder="https://github.com/Kitware/CDash"
              >
            </label>
            <h3 class="tw-text-xl tw-font-bold tw-mt-4">
              Issue Tracker
            </h3>
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Issue Tracker Type
              </span>
              <select
                v-model="form.bugTracker"
                class="tw-select tw-select-bordered"
              >
                <option :value="null">
                  None
                </option>
                <option value="GITHUB">
                  GitHub
                </option>
                <option value="JIRA">
                  JIRA
                </option>
                <option value="BUGANIZER">
                  Buganizer
                </option>
              </select>
            </label>
            <label
              v-show="form.bugTracker"
              class="tw-form-control tw-w-full"
            >
              <span class="tw-label tw-label-text tw-font-bold">
                Issue Tracker URL
              </span>
              <input
                v-model="form.bugTrackerUrl"
                type="text"
                class="tw-input tw-input-bordered tw-w-full"
                placeholder="https://github.com/Kitware/CDash/issues"
              >
            </label>
            <label
              v-show="form.bugTracker"
              class="tw-form-control tw-w-full"
            >
              <span class="tw-label tw-label-text tw-font-bold">
                New Issue URL
              </span>
              <input
                v-model="form.bugTrackerNewIssueUrl"
                type="text"
                class="tw-input tw-input-bordered tw-w-full"
                placeholder="https://github.com/Kitware/CDash/issues/new"
              >
            </label>
          </div>

          <div v-show="currentSection === 'notifications'">
            <h2 class="tw-text-2xl tw-font-bold">
              Notifications
            </h2>
            <div class="tw-divider" />
            <div class="tw-form-control">
              <label class="tw-cursor-pointer tw-label tw-justify-start tw-gap-2">
                <input
                  v-model="form.emailLowCoverage"
                  type="checkbox"
                  class="tw-toggle"
                >
                <span class="tw-label-text">Email on low coverage</span>
              </label>
              <label class="tw-cursor-pointer tw-label tw-justify-start tw-gap-2">
                <input
                  v-model="form.emailTestTimingChanged"
                  type="checkbox"
                  class="tw-toggle"
                >
                <span class="tw-label-text">Email on test timing changes</span>
              </label>
              <label class="tw-cursor-pointer tw-label tw-justify-start tw-gap-2">
                <input
                  v-model="form.emailBrokenSubmissions"
                  type="checkbox"
                  class="tw-toggle"
                >
                <span class="tw-label-text">Email on broken submissions</span>
              </label>
              <label class="tw-cursor-pointer tw-label tw-justify-start tw-gap-2">
                <input
                  v-model="form.emailRedundantFailures"
                  type="checkbox"
                  class="tw-toggle"
                >
                <span class="tw-label-text">Email on redundant failures</span>
              </label>
              <label class="tw-form-control tw-w-full">
                <span class="tw-label tw-label-text tw-font-bold">
                  Maximum items per email
                </span>
                <input
                  v-model="form.emailMaxItems"
                  type="number"
                  class="tw-input tw-input-bordered tw-w-full"
                >
              </label>
              <label class="tw-form-control tw-w-full">
                <span class="tw-label tw-label-text tw-font-bold">
                  Maximum characters per email
                </span>
                <input
                  v-model="form.emailMaxCharacters"
                  type="number"
                  class="tw-input tw-input-bordered tw-w-full"
                >
              </label>
            </div>
          </div>

          <div v-show="currentSection === 'coverage'">
            <h2 class="tw-text-2xl tw-font-bold">
              Coverage
            </h2>
            <div class="tw-divider" />
            <label class="tw-form-control tw-w-full">
              <span class="tw-label tw-label-text tw-font-bold">
                Threshold
              </span>
              <input
                v-model="form.coverageThreshold"
                type="number"
                class="tw-input tw-input-bordered tw-w-full"
              >
            </label>
            <div class="tw-form-control">
              <label class="tw-cursor-pointer tw-label tw-justify-start tw-gap-2">
                <input
                  v-model="form.showCoverageCode"
                  type="checkbox"
                  class="tw-toggle"
                >
                <span class="tw-label-text">Show Code</span>
              </label>
            </div>
          </div>

          <div v-show="currentSection === 'tests'">
            <h2 class="tw-text-2xl tw-font-bold">
              Tests
            </h2>
            <div class="tw-divider" />
            <div class="tw-form-control">
              <label class="tw-cursor-pointer tw-label tw-justify-start tw-gap-2">
                <input
                  v-model="form.enableTestTiming"
                  type="checkbox"
                  class="tw-toggle"
                >
                <span class="tw-label-text">Enable Test Timing</span>
              </label>
            </div>
            <div v-show="form.enableTestTiming">
              <label class="tw-form-control tw-w-full">
                <span class="tw-label tw-label-text tw-font-bold">
                  Test Time Standard Deviation Multiplier
                </span>
                <input
                  v-model="form.testTimeStdMultiplier"
                  type="number"
                  class="tw-input tw-input-bordered tw-w-full"
                >
              </label>
              <label class="tw-form-control tw-w-full">
                <span class="tw-label tw-label-text tw-font-bold">
                  Test Time Standard Deviation Threshold
                </span>
                <input
                  v-model="form.testTimeStdThreshold"
                  type="number"
                  class="tw-input tw-input-bordered tw-w-full"
                >
              </label>
              <label class="tw-form-control tw-w-full">
                <span class="tw-label tw-label-text tw-font-bold">
                  Time Status Failure Threshold
                </span>
                <input
                  v-model="form.timeStatusFailureThreshold"
                  type="number"
                  class="tw-input tw-input-bordered tw-w-full"
                >
              </label>
            </div>
          </div>

          <div class="tw-divider" />
          <div class="tw-flex tw-justify-start tw-mt-4">
            <button
              class="tw-btn tw-btn-primary"
              @click="updateProject"
            >
              Save
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import gql from 'graphql-tag';
import {
  faCircleXmark,
  faEarthAmericas,
  faShieldHalved,
  faLock,
} from '@fortawesome/free-solid-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import ProjectLogo from './shared/ProjectLogo.vue';

export default {
  components: {FontAwesomeIcon, ProjectLogo},
  props: {
    projectId: {
      type: Number,
      required: true,
    },
    ldapEnabled: {
      type: Boolean,
      required: true,
    },
  },

  data() {
    return {
      currentSection: 'details',
      form: {
        name: '',
        description: '',
        visibility: 'PRIVATE',
        authenticateSubmissions: false,
        homeUrl: '',
        vcsViewer: null,
        vcsUrl: '',
        bugTracker: null,
        bugTrackerUrl: '',
        bugTrackerNewIssueUrl: '',
        documentationUrl: '',
        testDataUrl: '',
        ldapFilter: '',
        coverageThreshold: 70,
        nightlyTime: '00:00:00',
        emailLowCoverage: false,
        emailTestTimingChanged: false,
        emailBrokenSubmissions: true,
        emailRedundantFailures: false,
        testTimeStdMultiplier: 4.0,
        testTimeStdThreshold: 1.0,
        enableTestTiming: true,
        timeStatusFailureThreshold: 0,
        emailMaxItems: 5,
        emailMaxCharacters: 255,
        displayLabels: true,
        autoRemoveTimeFrame: 0,
        autoRemoveMaxBuilds: 500,
        fileUploadLimit: 50,
        showCoverageCode: true,
        shareLabelFilters: false,
        showViewSubProjectsLink: true,
        banner: '',
      },
      validationErrors: {},
      fatalError: null,
    };
  },

  apollo: {
    project: {
      query: gql`
        query project($id: ID!) {
          project(id: $id) {
            id
            name
            description
            homeUrl
            vcsViewer
            vcsUrl
            bugTracker
            bugTrackerUrl
            bugTrackerNewIssueUrl
            documentationUrl
            testDataUrl
            visibility
            authenticateSubmissions
            ldapFilter
            coverageThreshold
            nightlyTime
            emailLowCoverage
            emailTestTimingChanged
            emailBrokenSubmissions
            emailRedundantFailures
            testTimeStdMultiplier
            testTimeStdThreshold
            enableTestTiming
            timeStatusFailureThreshold
            emailMaxItems
            emailMaxCharacters
            displayLabels
            autoRemoveTimeFrame
            autoRemoveMaxBuilds
            fileUploadLimit
            showCoverageCode
            shareLabelFilters
            showViewSubProjectsLink
            banner
            logoUrl
          }
        }
      `,
      variables() {
        return {
          id: this.projectId,
        };
      },
      result({ data }) {
        if (data && data.project) {
          this.form = { ...data.project };
          delete this.form.logoUrl;
          delete this.form.id;
        }
      },
    },
  },

  computed: {
    csrfToken() {
      return document.head.querySelector('meta[name="csrf-token"]').content;
    },
    FA() {
      return {
        faCircleXmark,
        faEarthAmericas,
        faShieldHalved,
        faLock,
      };
    },
  },

  methods: {
    async updateProject() {
      this.validationErrors = {};
      this.fatalError = null;
      try {
        const cleanForm = { ...this.form };
        delete cleanForm.__typename;
        await this.$apollo.mutate({
          mutation: gql`
            mutation updateProject($input: UpdateProjectInput!) {
              updateProject(input: $input) {
                project {
                  id
                }
              }
            }
          `,
          variables: {
            input: {
              id: this.projectId,
              ...cleanForm,
            },
          },
        });
        // TODO: Show a success message.
      } catch (error) {
        if (error.graphQLErrors) {
          error.graphQLErrors.forEach(e => {
            if (e.extensions && e.extensions.validation) {
              this.validationErrors = Object.keys(e.extensions.validation).reduce((acc, key) => {
                acc[key.replace('input.', '')] = e.extensions.validation[key];
                return acc;
              }, {});
            } else {
              this.fatalError = e.message;
            }
          });
        } else {
          this.fatalError = error.message;
        }
      }
    },
  },
};
</script>
