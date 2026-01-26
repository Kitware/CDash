<template>
  <div
    class="tw-flex tw-flex-row tw-w-full tw-justify-center"
    data-test="create-project-page"
  >
    <div class="tw-w-full sm:tw-w-3/4 lg:tw-w-1/2 tw-flex tw-flex-col tw-gap-2">
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
      <label class="tw-form-control tw-w-full">
        <span class="tw-label tw-label-text tw-font-bold">
          Project Name
        </span>
        <input
          v-model="name"
          type="text"
          class="tw-input tw-input-bordered tw-w-full"
          :class="{'tw-input-error': validationErrors.name}"
          data-test="project-name-input"
        >
        <span
          v-if="validationErrors.name"
          class="tw-label tw-text-error"
          data-test="project-name-validation-errors"
        >
          {{ validationErrors.name[0] }}
        </span>
      </label>
      <label class="tw-form-control tw-w-full">
        <span class="tw-label tw-label-textt tw-font-bold">
          Description
        </span>
        <textarea
          v-model="description"
          class="tw-textarea tw-textarea-bordered tw-h-24 tw-w-full"
          :class="{'tw-textarea-error': validationErrors.description}"
          data-test="project-description-input"
        />
        <span
          v-if="validationErrors.description"
          class="tw-label tw-text-error"
        >
          {{ validationErrors.description[0] }}
        </span>
      </label>
      <div class="tw-form-control tw-w-full">
        <span class="tw-label tw-label-text tw-font-bold">
          Visibility
        </span>
        <div class="tw-w-full tw-flex tw-flex-col tw-gap-1">
          <label class="tw-flex tw-items-center tw-gap-2">
            <input
              v-model="visibility"
              type="radio"
              name="visibility"
              class="tw-radio"
              value="PUBLIC"
              data-test="project-visibility-public"
              :disabled="maxProjectVisibility === 'PRIVATE' || maxProjectVisibility === 'PROTECTED'"
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
              v-model="visibility"
              type="radio"
              name="visibility"
              class="tw-radio"
              value="PROTECTED"
              data-test="project-visibility-protected"
              :disabled="maxProjectVisibility === 'PRIVATE'"
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
              v-model="visibility"
              type="radio"
              name="visibility"
              class="tw-radio"
              value="PRIVATE"
              data-test="project-visibility-private"
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
          Submission Authentication
        </span>
        <label class="tw-flex tw-items-center tw-gap-2">
          <input
            v-model="requireAuthenticatedSubmissions"
            type="checkbox"
            class="tw-checkbox"
            data-test="project-authenticated-submissions-input"
          >
          <span class="tw-label-text">
            Require submissions to provide a valid authentication token.
          </span>
        </label>
      </div>
      <div class="tw-flex tw-justify-start tw-mt-4">
        <button
          class="tw-btn tw-btn-success"
          data-test="create-project-button"
          @click="createProject"
        >
          Create Project
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import {
  faCircleXmark,
  faEarthAmericas,
  faShieldHalved,
  faLock,
} from '@fortawesome/free-solid-svg-icons';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import gql from 'graphql-tag';

export default {
  components: {FontAwesomeIcon},

  props: {
    maxProjectVisibility: {
      type: String,
      required: true,
    },
  },

  data() {
    return {
      name: '',
      description: '',
      visibility: 'PRIVATE',
      requireAuthenticatedSubmissions: false,
      validationErrors: {},
      fatalError: null,
    };
  },

  computed: {
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
    async createProject() {
      this.validationErrors = {};
      this.fatalError = null;
      try {
        const response = await this.$apollo.mutate({
          mutation: gql`
            mutation createProject($name: String!, $description: String, $visibility: ProjectVisibility!, $authenticateSubmissions: Boolean!) {
              createProject(input: {
                name: $name,
                description: $description,
                visibility: $visibility,
                authenticateSubmissions: $authenticateSubmissions,
              }) {
                id
              }
            }
          `,
          variables: {
            name: this.name,
            description: this.description,
            visibility: this.visibility,
            authenticateSubmissions: this.requireAuthenticatedSubmissions,
          },
        });

        if (response.data.createProject) {
          window.location.href = `${this.$baseURL}/projects/${response.data.createProject.id}/edit`;
        }
      }
      catch (error) {
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
    },
  },
};
</script>
