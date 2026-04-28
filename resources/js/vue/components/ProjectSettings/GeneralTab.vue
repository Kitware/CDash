<template>
  <LoadingIndicator :is-loading="!project">
    <TabContent
      title="General"
      data-test="general-tab"
    >
      <FormSection
        title="Project Information"
      >
        <div class="tw-flex tw-flex-row tw-gap-4">
          <InputField
            v-model="form.name"
            :validation-error="validationErrors?.name?.[0]"
            label="Project Name"
            test-id="name-input"
          />

          <form
            :action="`/projects/${projectId}/logo`"
            method="post"
            enctype="multipart/form-data"
          >
            <input
              type="hidden"
              name="_token"
              :value="csrfToken"
            >
            <span class="tw-label tw-label-text tw-font-bold">
              Logo
            </span>
            <span class="tw-flex-grow tw-flex tw-items-center tw-gap-2">
              <input
                type="file"
                name="logo"
                class="tw-file-input tw-file-input-bordered"
              >
              <button
                type="submit"
                class="tw-btn"
              >
                Upload
              </button>
            </span>
          </form>
        </div>

        <TextAreaField
          v-model="form.description"
          :validation-error="validationErrors?.description?.[0]"
          label="Description"
          test-id="description-input"
        />
      </FormSection>

      <FormSection
        title="Access Control"
      >
        <div class="tw-form-control tw-w-full tw-mb-2">
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

        <CheckboxField
          v-model="form.authenticateSubmissions"
          :validation-error="validationErrors?.authenticateSubmissions?.[0]"
          label="Authenticated Submissions"
          description="Require submissions to provide a valid authentication token."
          test-id="authenticated-submissions-input"
        />

        <InputField
          v-model="form.ldapFilter"
          :validation-error="validationErrors?.ldapFilter?.[0]"
          :disabled="!ldapEnabled"
          label="LDAP Filter"
          description="A LDAP group users must be a member of to access the project. Requires LDAP to be configured."
          test-id="ldap-filter-input"
        />
      </FormSection>

      <FormSection
        title="Display Options"
      >
        <TextAreaField
          v-model="form.banner"
          :validation-error="validationErrors?.banner?.[0]"
          label="Banner Message"
          test-id="banner-input"
        />

        <CheckboxField
          v-model="form.displayLabels"
          :validation-error="validationErrors?.displayLabels?.[0]"
          label="Display Labels"
          test-id="display-labels-input"
        />
      </FormSection>

      <FormSection
        title="Build Configuration"
      >
        <InputField
          v-model="form.nightlyTime"
          :validation-error="validationErrors?.nightlyTime?.[0]"
          label="Nightly Time"
          test-id="nightly-time-input"
        />

        <InputField
          v-model="form.autoRemoveTimeFrame"
          :validation-error="validationErrors?.autoRemoveTimeFrame?.[0]"
          label="Build Retention (Days)"
          type="number"
          min="0"
          test-id="autoremove-time-frame-input"
        />

        <InputField
          v-model="form.fileUploadLimit"
          :validation-error="validationErrors?.fileUploadLimit?.[0]"
          label="File Upload Limit (GB)"
          type="number"
          min="0"
          test-id="file-upload-limit-input"
        />
      </FormSection>

      <FormSection
        title="External Resources"
      >
        <InputField
          v-model="form.homeUrl"
          :validation-error="validationErrors?.homeUrl?.[0]"
          label="Home URL"
          placeholder="https://example.com"
          test-id="home-url-input"
        />

        <InputField
          v-model="form.documentationUrl"
          :validation-error="validationErrors?.documentationUrl?.[0]"
          label="Documentation URL"
          placeholder="https://example.com"
          test-id="documentation-url-input"
        />

        <InputField
          v-model="form.testDataUrl"
          :validation-error="validationErrors?.testDataUrl?.[0]"
          label="Test Data URL"
          placeholder="https://example.com"
          test-id="test-data-url-input"
        />

        <div class="tw-flex tw-flex-row tw-gap-4">
          <SelectField
            v-model="form.vcsViewer"
            :validation-error="validationErrors?.vcsViewer?.[0]"
            label="Repository Type"
            :options="[
              {
                text: 'None',
                value: null,
              },
              {
                text: 'GitHub',
                value: 'GITHUB',
              },
              {
                text: 'GitLab',
                value: 'GITLAB',
              },
            ]"
            test-id="vcs-viewer-input"
          />

          <InputField
            v-model="form.vcsUrl"
            :validation-error="validationErrors?.vcsUrl?.[0]"
            label="Repository URL"
            placeholder="https://example.com"
            test-id="vcs-url-input"
          />

          <InputField
            v-model="form.cmakeProjectRoot"
            :validation-error="validationErrors?.cmakeProjectRoot?.[0]"
            label="CMake Project Root "
            placeholder="/src"
            test-id="cmake-project-root-input"
          />
        </div>

        <div class="tw-flex tw-flex-row tw-gap-4">
          <SelectField
            v-model="form.bugTracker"
            :validation-error="validationErrors?.bugTracker?.[0]"
            label="Issue Tracker Type"
            :options="[
              {
                text: 'None',
                value: null,
              },
              {
                text: 'GitHub',
                value: 'GITHUB',
              },
              {
                text: 'JIRA',
                value: 'JIRA',
              },
              {
                text: 'Buganizer',
                value: 'BUGANIZER',
              },
            ]"
            test-id="bug-tracker-input"
          />

          <InputField
            v-model="form.bugTrackerUrl"
            :validation-error="validationErrors?.bugTrackerUrl?.[0]"
            label="Issue Tracker URL"
            placeholder="https://example.com"
            test-id="bug-tracker-url-input"
          />

          <InputField
            v-model="form.bugTrackerNewIssueUrl"
            :validation-error="validationErrors?.bugTrackerNewIssueUrl?.[0]"
            label="Issue Tracker New Issue URL"
            placeholder="https://example.com"
            test-id="bug-tracker-new-issue-url-input"
          />
        </div>
      </FormSection>

      <FormSection
        title="Notifications"
      >
        <CheckboxField
          v-model="form.emailLowCoverage"
          :validation-error="validationErrors?.emailLowCoverage?.[0]"
          label="Email on Low Coverage"
          test-id="email-low-coverage-input"
        />

        <CheckboxField
          v-model="form.emailTestTimingChanged"
          :validation-error="validationErrors?.emailTestTimingChanged?.[0]"
          label="Email on Test Timing Changes"
          test-id="email-test-timing-changed-input"
        />

        <CheckboxField
          v-model="form.emailBrokenSubmissions"
          :validation-error="validationErrors?.emailBrokenSubmissions?.[0]"
          label="Email on Broken Submissions"
          test-id="email-broken-submissions-input"
        />

        <CheckboxField
          v-model="form.emailRedundantFailures"
          :validation-error="validationErrors?.emailRedundantFailures?.[0]"
          label="Email on Redundant Failures"
          test-id="email-redundant-failures-input"
        />

        <InputField
          v-model="form.emailMaxItems"
          :validation-error="validationErrors?.emailMaxItems?.[0]"
          label="Maximum Items Per Email"
          type="number"
          min="1"
          test-id="email-max-items-input"
        />

        <InputField
          v-model="form.emailMaxCharacters"
          :validation-error="validationErrors?.emailMaxCharacters?.[0]"
          label="Maximum Characters Per Email"
          type="number"
          min="1"
          test-id="email-max-characters-input"
        />
      </FormSection>

      <FormSection
        title="Coverage"
      >
        <InputField
          v-model="form.coverageThreshold"
          :validation-error="validationErrors?.coverageThreshold?.[0]"
          label="Coverage Threshold"
          description="Coverage values greater than this threshold will be displayed in green."
          type="number"
          min="0"
          test-id="coverage-threshold-input"
        />

        <CheckboxField
          v-model="form.showCoverageCode"
          :validation-error="validationErrors?.showCoverageCode?.[0]"
          label="Show Source Code"
          description="Select whether users can see the source code in the coverage section."
          test-id="show-coverage-code-input"
        />
      </FormSection>

      <FormSection
        title="Tests"
      >
        <CheckboxField
          v-model="form.enableTestTiming"
          :validation-error="validationErrors?.enableTestTiming?.[0]"
          label="Enable Test Timing"
          test-id="enable-test-timing-input"
        />

        <InputField
          v-model="form.timeStatusFailureThreshold"
          :validation-error="validationErrors?.timeStatusFailureThreshold?.[0]"
          :disabled="!form.enableTestTiming"
          label="Time Status Failure Threshold"
          description="
            The number of times a test must violate the time status check before it is flagged as a
            failure. For example, if this is set to 2, then a test will need to run more slowly than
            expected twice in a row before it is marked as having failed the time status check.
          "
          type="number"
          min="1"
          test-id="time-status-failure-threshold-input"
        />

        <InputField
          v-model="form.testTimeStdThreshold"
          :validation-error="validationErrors?.testTimeStdThreshold?.[0]"
          :disabled="!form.enableTestTiming"
          label="Time Status Standard Deviation Threshold"
          description="
            Set a minimum standard deviation for a test time failure. If the current standard deviation
            for a test is lower than this threshold then the threshold is used instead. This is
            particularly important for tests that have a very low standard deviation but still some
            variability. Note that changing this value doesn’t affect previous builds.
          "
          type="number"
          min="0"
          test-id="test-time-std-threshold-input"
        />

        <InputField
          v-model="form.testTimeStdMultiplier"
          :validation-error="validationErrors?.testTimeStdMultiplier?.[0]"
          :disabled="!form.enableTestTiming"
          label="Time Status Standard Deviation Multiplier"
          description="
            Set a multiplier for the standard deviation for a test time. If the time for a test is
            higher than mean+multiplier*standarddeviation, the test time status is marked as failed.
            Note that changing this value doesn’t affect previous builds.
          "
          type="number"
          min="0"
          test-id="test-time-std-multiplier-input"
        />
      </FormSection>

      <FormSection title="">
        <div class="tw-flex tw-items-center tw-justify-start tw-mt-4 tw-gap-4">
          <button
            class="tw-btn tw-btn-success tw-min-w-32"
            :disabled="updateProjectLoading"
            data-test="save-button"
            @click="updateProject"
          >
            <span
              v-if="updateProjectLoading"
              class="tw-loading tw-loading-spinner"
            />
            Save
          </button>
          <div
            v-if="projectSaved"
            class="tw-text-success"
            data-test="success-message"
          >
            <FontAwesomeIcon :icon="FA.faCheck" /> Changes Saved
          </div>
          <div
            v-if="projectUpdateFailed"
            class="tw-text-error"
            data-test="error-message"
          >
            <FontAwesomeIcon :icon="FA.faCircleXmark" />
            <template v-if="fatalError">
              {{ fatalError }}
            </template>
            <template v-else>
              Failed to save project.
            </template>
          </div>
        </div>
      </FormSection>
    </TabContent>
  </LoadingIndicator>
</template>

<script>
import FormSection from '../shared/FormSection.vue';
import gql from 'graphql-tag';
import {
  faCircleXmark,
  faEarthAmericas,
  faShieldHalved,
  faLock,
  faDisplay,
  faInfo,
  faGears,
  faLink,
  faCheck,
} from '@fortawesome/free-solid-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import InputField from '../shared/FormInputs/InputField.vue';
import TextAreaField from '../shared/FormInputs/TextAreaField.vue';
import CheckboxField from '../shared/FormInputs/CheckboxField.vue';
import SelectField from '../shared/FormInputs/SelectField.vue';
import TabContent from './TabContent.vue';
import LoadingIndicator from '../shared/LoadingIndicator.vue';

export default {
  components: {
    LoadingIndicator,
    TabContent, SelectField, CheckboxField, TextAreaField, InputField, FontAwesomeIcon, FormSection},
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
      updateProjectLoading: false,
      projectSaved: false,
      projectUpdateFailed: false,
      form: {
        name: '',
        description: '',
        visibility: 'PRIVATE',
        authenticateSubmissions: false,
        homeUrl: '',
        vcsViewer: null,
        vcsUrl: '',
        cmakeProjectRoot: '',
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
        fileUploadLimit: 50,
        showCoverageCode: true,
        shareLabelFilters: false,
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
            cmakeProjectRoot
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
            fileUploadLimit
            showCoverageCode
            shareLabelFilters
            banner
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
        faDisplay,
        faInfo,
        faGears,
        faLink,
        faCheck,
      };
    },
  },

  methods: {
    async updateProject() {
      this.updateProjectLoading = true;
      this.projectSaved = false;
      this.projectUpdateFailed = false;

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
        this.projectSaved = true;
      }
      catch (error) {
        this.projectUpdateFailed = true;
        if (error.graphQLErrors) {
          error.graphQLErrors.forEach(e => {
            if (e.extensions && e.extensions.validation) {
              this.validationErrors = Object.keys(e.extensions.validation).reduce((acc, key) => {
                acc[key.replace('input.', '')] = e.extensions.validation[key];
                return acc;
              }, {});
            }
            else {
              this.fatalError = e.message;
            }
          });
        }
        else {
          this.fatalError = error.message;
        }
      }

      this.updateProjectLoading = false;
    },
  },
};
</script>
