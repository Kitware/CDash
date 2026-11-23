<template>
  <div
    class="tw-flex tw-flex-row tw-flex-wrap tw-items-center tw-gap-2 tw-mt-2"
    data-test="project-metrics"
  >
    <template v-if="!editing">
      <div
        v-for="metric in shownMetrics"
        :key="metric.id"
        class="tw-flex tw-flex-col tw-items-center tw-text-center tw-border tw-border-base-300 tw-rounded-lg tw-bg-gray-50 tw-p-2"
        :class="{ 'tw-opacity-50': !metric.latestValue }"
        :title="updatedText(metric)"
        data-test="project-metric"
      >
        <span class="tw-text-xs tw-text-neutral-500">
          {{ metric.name }}
        </span>
        <span class="tw-font-medium">
          {{ metric.latestValue?.value ?? 'No values' }}
        </span>
      </div>
      <button
        v-if="canUpdate && metrics.length === 0"
        type="button"
        class="tw-btn tw-btn-sm tw-btn-outline tw-border-dashed tw-border-base-300 tw-font-normal"
        data-test="add-metrics-button"
        @click="startEditing(true)"
      >
        <FontAwesomeIcon :icon="FA.faPlus" />
        Add metrics
      </button>
      <button
        v-else-if="canUpdate"
        type="button"
        class="tw-btn tw-btn-ghost tw-btn-xs tw-text-neutral-500"
        title="Edit metrics"
        aria-label="Edit metrics"
        data-test="edit-metrics-button"
        @click="startEditing(false)"
      >
        <FontAwesomeIcon :icon="FA.faPen" />
      </button>
    </template>

    <!-- Only the cards can be dragged, not the controls after them. -->
    <draggable
      v-else
      v-model="orderedMetrics"
      item-key="id"
      handle=".tw-handle"
      draggable=".tw-metric-card"
      tag="div"
      class="tw-flex tw-flex-row tw-flex-wrap tw-items-center tw-gap-2"
      @end="saveOrder"
    >
      <template #item="{ element: metric }">
        <div
          class="tw-metric-card tw-flex tw-flex-col tw-items-center tw-gap-1 tw-text-center tw-border tw-border-base-300 tw-rounded-lg tw-bg-gray-50 tw-p-2"
          data-test="edit-project-metric"
        >
          <div class="tw-flex tw-flex-row tw-items-center tw-gap-1">
            <FontAwesomeIcon
              :icon="FA.faGripVertical"
              class="tw-handle tw-cursor-move tw-text-neutral-400"
              title="Drag to reorder"
            />
            <input
              v-if="renaming?.id === metric.id"
              :ref="setRenameInput"
              v-model="renameValue"
              type="text"
              maxlength="25"
              class="tw-input tw-input-bordered tw-input-xs tw-w-40"
              :class="{ 'tw-input-error': renameError }"
              :aria-invalid="renameError ? 'true' : 'false'"
              aria-label="Metric name"
              data-test="rename-metric-input"
              @keydown.enter.prevent="saveRename"
              @keydown.esc="cancelRename"
            >
            <span
              v-else
              class="tw-text-xs tw-text-neutral-500"
            >
              {{ metric.name }}
            </span>
          </div>
          <span
            v-if="renaming?.id === metric.id && renameError"
            class="tw-text-xs tw-text-error"
            data-test="rename-metric-error"
          >
            {{ renameError }}
          </span>
          <span
            class="tw-font-medium"
            :class="{ 'tw-text-neutral-400': !metric.latestValue }"
          >
            {{ metric.latestValue?.value ?? 'No values' }}
          </span>
          <div class="tw-flex tw-flex-row tw-gap-1">
            <template v-if="renaming?.id === metric.id">
              <button
                type="button"
                class="tw-btn tw-btn-ghost tw-btn-xs"
                title="Save name"
                aria-label="Save name"
                :disabled="busy"
                data-test="save-metric-name-button"
                @click="saveRename"
              >
                <FontAwesomeIcon :icon="FA.faCheck" />
              </button>
              <button
                type="button"
                class="tw-btn tw-btn-ghost tw-btn-xs"
                title="Cancel"
                aria-label="Cancel renaming"
                @click="cancelRename"
              >
                <FontAwesomeIcon :icon="FA.faXmark" />
              </button>
            </template>
            <template v-else>
              <button
                type="button"
                class="tw-btn tw-btn-ghost tw-btn-xs tw-text-neutral-500"
                title="Rename"
                :aria-label="`Rename ${metric.name}`"
                data-test="rename-metric-button"
                @click="startRename(metric)"
              >
                <FontAwesomeIcon :icon="FA.faPen" />
              </button>
              <button
                type="button"
                class="tw-btn tw-btn-ghost tw-btn-xs tw-text-neutral-500"
                title="Delete"
                :aria-label="`Delete ${metric.name}`"
                data-test="delete-metric-button"
                @click="confirmDelete(metric)"
              >
                <FontAwesomeIcon :icon="FA.faTrash" />
              </button>
            </template>
          </div>
        </div>
      </template>

      <template #footer>
        <div
          v-if="adding"
          class="tw-flex tw-flex-col tw-items-center tw-gap-1 tw-border tw-border-dashed tw-border-base-300 tw-rounded-lg tw-p-2"
        >
          <input
            :ref="setNewInput"
            v-model="newName"
            type="text"
            maxlength="25"
            placeholder="Metric name"
            class="tw-input tw-input-bordered tw-input-xs tw-w-40"
            :class="{ 'tw-input-error': addError }"
            :aria-invalid="addError ? 'true' : 'false'"
            aria-label="New metric name"
            data-test="new-metric-input"
            @keydown.enter.prevent="createMetric"
            @keydown.esc="cancelAdd"
          >
          <span
            v-if="addError"
            class="tw-text-xs tw-text-error"
            data-test="new-metric-error"
          >
            {{ addError }}
          </span>
          <div class="tw-flex tw-flex-row tw-gap-1">
            <button
              type="button"
              class="tw-btn tw-btn-ghost tw-btn-xs"
              title="Add"
              aria-label="Add metric"
              :disabled="busy"
              data-test="save-new-metric-button"
              @click="createMetric"
            >
              <FontAwesomeIcon :icon="FA.faCheck" />
            </button>
            <button
              type="button"
              class="tw-btn tw-btn-ghost tw-btn-xs"
              title="Cancel"
              aria-label="Cancel adding a metric"
              @click="cancelAdd"
            >
              <FontAwesomeIcon :icon="FA.faXmark" />
            </button>
          </div>
        </div>
        <button
          v-else
          type="button"
          class="tw-btn tw-btn-sm tw-btn-outline tw-border-dashed tw-border-base-300 tw-font-normal"
          data-test="add-metric-button"
          @click="startAdd"
        >
          <FontAwesomeIcon :icon="FA.faPlus" />
          Add metric
        </button>
        <button
          type="button"
          class="tw-btn tw-btn-sm tw-btn-primary"
          data-test="done-editing-metrics-button"
          @click="stopEditing"
        >
          Done
        </button>
      </template>
    </draggable>

    <div
      v-if="generalError"
      role="alert"
      class="tw-basis-full tw-text-sm tw-text-error"
      data-test="project-metrics-error"
    >
      <FontAwesomeIcon :icon="FA.faCircleExclamation" />
      {{ generalError }}
    </div>

    <dialog
      ref="deleteDialog"
      class="tw-modal"
      data-test="delete-metric-dialog"
    >
      <div class="tw-modal-box tw-flex tw-flex-col tw-gap-4">
        <h3 class="tw-text-lg tw-font-bold tw-m-0">
          Delete metric
        </h3>
        <p class="tw-m-0">
          {{ deleteMessage }}
        </p>
        <div class="tw-modal-action">
          <button
            type="button"
            class="tw-btn"
            data-test="cancel-delete-metric-button"
            @click="$refs.deleteDialog.close()"
          >
            Cancel
          </button>
          <button
            type="button"
            class="tw-btn tw-btn-error"
            :disabled="busy"
            data-test="confirm-delete-metric-button"
            @click="deleteMetric"
          >
            Delete
          </button>
        </div>
      </div>
      <form
        method="dialog"
        class="tw-modal-backdrop"
      >
        <button>Close</button>
      </form>
    </dialog>
  </div>
</template>

<script>
import gql from 'graphql-tag';
import draggable from 'vuedraggable';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
  faCheck,
  faCircleExclamation,
  faGripVertical,
  faPen,
  faPlus,
  faTrash,
  faXmark,
} from '@fortawesome/free-solid-svg-icons';
import Utils from '../shared/Utils';

export default {
  name: 'ProjectMetrics',

  components: {
    draggable,
    FontAwesomeIcon,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },

    // Ordered by position, each with its latest value, or null if no values have been submitted.
    metrics: {
      type: Array,
      required: true,
    },

    canUpdate: {
      type: Boolean,
      default: false,
    },
  },

  emits: {
    /** Emitted after every change, so that the page reloads the metrics. */
    changed: () => true,
  },

  data() {
    return {
      editing: false,
      // A copy of the metrics which the cards can be dragged around in.
      orderedMetrics: [...this.metrics],
      busy: false,
      generalError: null,
      renaming: null,
      renameValue: '',
      renameError: null,
      adding: false,
      newName: '',
      addError: null,
      deleting: null,
    };
  },

  computed: {
    FA() {
      return {
        faCheck,
        faCircleExclamation,
        faGripVertical,
        faPen,
        faPlus,
        faTrash,
        faXmark,
      };
    },

    // Editors also see the metrics which have no values yet, so that new metrics don't seem to disappear.
    shownMetrics() {
      return this.canUpdate ? this.metrics : this.metrics.filter((metric) => metric.latestValue);
    },

    deleteMessage() {
      if (!this.deleting) {
        return '';
      }
      const count = this.deleting.valueCount;
      if (count === 0) {
        return `Delete ${this.deleting.name}?`;
      }
      return `Delete ${this.deleting.name} and its ${count} ${count === 1 ? 'value' : 'values'}? This can't be undone.`;
    },
  },

  watch: {
    metrics(metrics) {
      this.orderedMetrics = [...metrics];
    },
  },

  methods: {
    updatedText(metric) {
      if (!metric.latestValue) {
        return 'No values have been submitted for this metric yet.';
      }
      return `Updated ${Utils.formatRelativeTimestamp(metric.latestValue.createdAt)}`;
    },

    startEditing(add) {
      this.editing = true;
      this.generalError = null;
      if (add) {
        this.startAdd();
      }
    },

    stopEditing() {
      this.editing = false;
      this.generalError = null;
      this.cancelRename();
      this.cancelAdd();
    },

    setRenameInput(element) {
      this.renameInput = element;
    },

    setNewInput(element) {
      this.newInput = element;
    },

    async startRename(metric) {
      this.cancelAdd();
      this.renaming = metric;
      this.renameValue = metric.name;
      this.renameError = null;
      await this.$nextTick();
      this.renameInput?.focus();
    },

    cancelRename() {
      this.renaming = null;
      this.renameError = null;
    },

    async saveRename() {
      const name = this.renameValue.trim();
      if (name === '') {
        this.renameError = 'Enter a name for the metric.';
        return;
      }
      if (name === this.renaming.name) {
        this.cancelRename();
        return;
      }

      const renamed = await this.runMutation(gql`
        mutation updateProjectMetric($input: UpdateProjectMetricInput!) {
          updateProjectMetric(input: $input) {
            message
          }
        }
      `, {
        id: this.renaming.id,
        name: name,
      }, (error) => {
        this.renameError = error;
      });
      if (renamed) {
        this.cancelRename();
      }
    },

    async startAdd() {
      this.cancelRename();
      this.adding = true;
      this.newName = '';
      this.addError = null;
      await this.$nextTick();
      this.newInput?.focus();
    },

    cancelAdd() {
      this.adding = false;
      this.addError = null;
    },

    async createMetric() {
      const name = this.newName.trim();
      if (name === '') {
        this.addError = 'Enter a name for the metric.';
        return;
      }

      this.addError = null;
      const created = await this.runMutation(gql`
        mutation createProjectMetric($input: CreateProjectMetricInput!) {
          createProjectMetric(input: $input) {
            message
          }
        }
      `, {
        projectId: this.projectId,
        name: name,
      }, (error) => {
        this.addError = error;
      });

      // Leave the input open, so that several metrics can be added in a row.
      if (created) {
        this.newName = '';
        this.newInput?.focus();
      }
    },

    confirmDelete(metric) {
      this.cancelRename();
      this.cancelAdd();
      this.deleting = metric;
      this.$refs.deleteDialog.showModal();
    },

    async deleteMetric() {
      await this.runMutation(gql`
        mutation deleteProjectMetric($input: DeleteProjectMetricInput!) {
          deleteProjectMetric(input: $input) {
            message
          }
        }
      `, {
        id: this.deleting.id,
      });
      this.$refs.deleteDialog.close();
    },

    async saveOrder(event) {
      if (event.oldIndex === event.newIndex) {
        return;
      }

      await this.runMutation(gql`
        mutation updateProjectMetricOrder($input: UpdateProjectMetricOrderInput!) {
          updateProjectMetricOrder(input: $input) {
            message
          }
        }
      `, {
        projectId: this.projectId,
        projectMetricIds: this.orderedMetrics.map((metric) => metric.id),
      });
    },

    /**
     * Save one change, then reload the metrics, even if the change failed, so that the cards match the server.
     *
     * @param {Object} mutation
     * @param {Object} input
     * @param {Function} onNameError Called with the error message for an invalid name.
     * @returns {Promise<boolean>} Whether the change was saved.
     */
    async runMutation(mutation, input, onNameError = () => {}) {
      this.busy = true;
      this.generalError = null;
      try {
        await this.$apollo.mutate({
          mutation: mutation,
          variables: {
            input: input,
          },
        });
        return true;
      } catch (error) {
        this.handleError(error, onNameError);
        return false;
      } finally {
        this.busy = false;
        this.$emit('changed');
      }
    },

    handleError(error, onNameError) {
      const graphQLErrors = error?.graphQLErrors ?? [];

      if (graphQLErrors.length === 0) {
        this.generalError = 'Your changes could not be saved. Check your connection and try again.';
        return;
      }

      const messages = [];
      for (const graphQLError of graphQLErrors) {
        const validation = graphQLError.extensions?.validation;
        if (validation) {
          for (const [key, fieldMessages] of Object.entries(validation)) {
            if (key.replace(/^input\./, '') === 'name') {
              onNameError(fieldMessages[0]);
            } else {
              messages.push(...fieldMessages);
            }
          }
        } else if (graphQLError.message === 'This action is unauthorized.') {
          // An expired session also ends up here, since the request is then made as a guest.
          messages.push('You do not have permission to edit this project. If you were signed out, reload the page and sign in again.');
        } else if (graphQLError.message === 'IDs for all ProjectMetrics must be provided.') {
          messages.push('The metrics were changed by someone else, so they have been reloaded. Try again.');
        } else {
          messages.push(graphQLError.message);
        }
      }

      this.generalError = messages.length > 0 ? [...new Set(messages)].join(' ') : null;
    },
  },
};
</script>
