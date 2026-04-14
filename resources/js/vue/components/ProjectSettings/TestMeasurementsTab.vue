<template>
  <TabContent
    title="Test Measurements"
    data-test="test-measurements-tab"
  >
    <LoadingIndicator :is-loading="!project">
      <draggable
        v-if="testMeasurements.length > 0"
        v-model="testMeasurements"
        item-key="id"
        handle=".tw-handle"
        tag="div"
        class="tw-flex tw-flex-col tw-gap-3"
        @end="updateOrder"
      >
        <template #item="{ element }">
          <div
            :data-test="'test-measurement-row-' + element.id"
            class="tw-flex tw-flex-row tw-items-center tw-justify-between tw-p-3 tw-border tw-border-base-300 tw-rounded-lg tw-bg-base-100"
          >
            <div class="tw-flex tw-items-center tw-gap-4 tw-flex-grow">
              <font-awesome-icon
                :icon="FA.faBars"
                class="tw-handle tw-cursor-move tw-text-neutral-500"
              />
              <div class="tw-font-bold">
                {{ element.name }}
              </div>
            </div>
            <button
              class="tw-btn tw-btn-ghost tw-btn-square tw-btn-sm"
              data-test="delete-test-measurement-button"
              @click="deleteTestMeasurement(element.id)"
            >
              <font-awesome-icon :icon="FA.faTrash" />
            </button>
          </div>
        </template>
      </draggable>
      <div
        v-else
        class="tw-text-center tw-py-8 tw-bg-base-200 tw-rounded-box"
        data-test="no-test-measurements-message"
      >
        No pinned test measurements yet.
      </div>
    </LoadingIndicator>

    <div class="tw-divider tw-my-0" />

    <div class="tw-flex tw-flex-col tw-gap-4">
      <InputField
        v-model="newTestMeasurementName"
        label="Test Measurement Name"
        placeholder="Test Measurement Name"
        test-id="new-test-measurement-input"
        @keyup.enter="createTestMeasurement"
      />
      <div class="tw-flex tw-items-center tw-justify-start">
        <button
          class="tw-btn tw-btn-primary tw-btn-sm"
          :disabled="!newTestMeasurementName || createLoading"
          data-test="add-test-measurement-button"
          @click="createTestMeasurement"
        >
          <span
            v-if="createLoading"
            class="tw-loading tw-loading-spinner"
          />
          <font-awesome-icon :icon="FA.faPlus" />
          Add
        </button>
      </div>
      <div
        v-if="errorMessage"
        class="tw-text-error tw-text-sm"
        data-test="error-message"
      >
        <font-awesome-icon :icon="FA.faCircleXmark" /> {{ errorMessage }}
      </div>
    </div>

    <div class="tw-text-sm">
      <ul class="tw-list-disc tw-ml-4">
        <li>
          Add test measurements of type <span class="tw-font-mono">numeric/double</span> or <span class="tw-font-mono">text/string</span>. Any measurement added here will be displayed as an extra column on the following pages:
          <ul class="tw-list-circle tw-ml-4">
            <li><span class="tw-font-mono">queryTests.php</span></li>
            <li><span class="tw-font-mono">viewTest.php</span></li>
            <li><span class="tw-font-mono">builds/&lt;id&gt;/tests</span></li>
          </ul>
        </li>
        <li>Other types of test measurements (eg. <span class="tw-font-mono">image/png</span>) are not supported for display on these pages, and they may not be rendered correctly if added here.</li>
        <li>You can drag and drop test measurements to change the order in which they are displayed.</li>
        <li>Note that all test measurements are shown on the "Test Details" page (<span class="tw-font-mono">/tests/{id}</span>), regardless of whether they have been added here.</li>
      </ul>
    </div>
  </TabContent>
</template>

<script>
import gql from 'graphql-tag';
import draggable from 'vuedraggable';
import { faBars, faTrash, faCheck, faCircleXmark, faPlus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import LoadingIndicator from '../shared/LoadingIndicator.vue';
import TabContent from './TabContent.vue';
import InputField from '../shared/FormInputs/InputField.vue';

export default {
  components: {
    LoadingIndicator,
    TabContent,
    InputField,
    draggable,
    FontAwesomeIcon,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      testMeasurements: [],
      newTestMeasurementName: '',
      project: null,
      createLoading: false,
      errorMessage: '',
    };
  },

  apollo: {
    project: {
      query: gql`
        query project($id: ID!) {
          project(id: $id) {
            id
            pinnedTestMeasurements {
              edges {
                node {
                  id
                  name
                  position
                }
              }
            }
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
          this.testMeasurements = [...data.project.pinnedTestMeasurements.edges.map(edge => edge.node)].sort((a, b) => a.position - b.position);
        }
      },
    },
  },

  computed: {
    FA() {
      return {
        faBars,
        faTrash,
        faCheck,
        faCircleXmark,
        faPlus,
      };
    },
  },

  methods: {
    async createTestMeasurement() {
      if (!this.newTestMeasurementName) {
        return;
      }
      this.createLoading = true;
      this.errorMessage = '';
      try {
        const result = await this.$apollo.mutate({
          mutation: gql`
            mutation createPinnedTestMeasurement($input: CreatePinnedTestMeasurementInput!) {
              createPinnedTestMeasurement(input: $input) {
                pinnedTestMeasurement {
                  id
                  name
                  position
                }
                message
              }
            }
          `,
          variables: {
            input: {
              projectId: this.projectId,
              name: this.newTestMeasurementName,
            },
          },
        });
        if (result.data.createPinnedTestMeasurement.message) {
          this.errorMessage = result.data.createPinnedTestMeasurement.message;
        }
        else {
          this.newTestMeasurementName = '';
          this.$apollo.queries.project.refetch();
        }
      }
      catch (error) {
        this.errorMessage = error.message;
      }
      finally {
        this.createLoading = false;
      }
    },

    async deleteTestMeasurement(id) {
      this.errorMessage = '';
      try {
        const result = await this.$apollo.mutate({
          mutation: gql`
            mutation deletePinnedTestMeasurement($input: DeletePinnedTestMeasurementInput!) {
              deletePinnedTestMeasurement(input: $input) {
                message
              }
            }
          `,
          variables: {
            input: {
              id: id,
            },
          },
        });
        if (result.data.deletePinnedTestMeasurement.message) {
          this.errorMessage = result.data.deletePinnedTestMeasurement.message;
        }
        else {
          this.$apollo.queries.project.refetch();
        }
      }
      catch (error) {
        this.errorMessage = error.message;
      }
    },

    async updateOrder() {
      this.errorMessage = '';
      const pinnedTestMeasurementIds = this.testMeasurements.map(m => m.id);
      try {
        const result = await this.$apollo.mutate({
          mutation: gql`
            mutation updatePinnedTestMeasurementOrder($input: UpdatePinnedTestMeasurementOrderInput!) {
              updatePinnedTestMeasurementOrder(input: $input) {
                pinnedTestMeasurements {
                  id
                  name
                  position
                }
                message
              }
            }
          `,
          variables: {
            input: {
              projectId: this.projectId,
              pinnedTestMeasurementIds,
            },
          },
        });
        if (result.data.updatePinnedTestMeasurementOrder.message) {
          this.errorMessage = result.data.updatePinnedTestMeasurementOrder.message;
          this.$apollo.queries.project.refetch();
        }
      }
      catch (error) {
        this.errorMessage = error.message;
        this.$apollo.queries.project.refetch();
      }
    },
  },
};
</script>
