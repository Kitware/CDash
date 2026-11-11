<template>
  <tr>
    <td
      v-if="selecting"
      align="center"
      class="paddt"
    >
      <input
        v-if="build.id"
        type="checkbox"
        :checked="selected"
        data-test="build-selection-checkbox"
        @change="$emit('update:selected', $event.target.checked)"
      >
    </td>

    <!-- A parent build's children are identified by their SubProject. -->
    <td
      v-if="settings.childView"
      align="left"
      class="paddt"
      colspan="2"
      data-test="subproject"
    >
      <a
        class="cdash-link"
        data-test="build-summary-link"
        :href="`builds/${build.id}`"
      >
        {{ build.label }}
      </a>
      <!-- Margins stand in for the spaces between elements which Vue's templates leave out. -->
      <a
        v-if="hasProblems"
        class="cdash-link"
        href="#"
        style="margin-left: 0.25em;"
        data-test="build-problems-toggle"
        @click.prevent="toggleProblems"
      >
        <span
          class="glyphicon glyphicon-info-sign"
          style="color: #777;"
          title="Build information"
        />
      </a>
      <a
        v-if="build.notes > 0"
        class="cdash-link"
        style="margin-left: 0.25em;"
        title="View notes"
        name="notesLink"
        data-test="build-notes-link"
        :href="`builds/${build.id}/notes`"
      >
        <span
          class="glyphicon glyphicon-file"
          style="color: #777;"
        />
      </a>
      <BuildProblems
        v-if="showProblems"
        :problems="problems"
        :project-name="settings.projectName"
      />
    </td>

    <template v-else>
      <td
        align="left"
        class="paddt"
        data-test="site"
      >
        <a
          class="cdash-link"
          :href="`sites/${build.siteid}?project=${settings.projectId}&currenttime=${settings.unixTimestamp}`"
        >
          {{ build.site }}
        </a>
        <span
          v-if="Number(build.siteoutoforder) === 1"
          data-test="site-out-of-order"
          class="glyphicon glyphicon-flag"
          style="color: #d9534f; margin-left: 0.25em;"
          title="Site out of order"
        />
      </td>

      <td
        align="left"
        data-test="build-name"
      >
        <div
          v-if="build.id && build.buildplatform"
          style="float: left;"
        >
          <img
            class="icon"
            alt="platform"
            :src="`img/platform_${build.buildplatform}.png`"
          >
        </div>

        <div
          style="float: left; margin: 0 4px;"
          data-test="build-name-text"
        >
          <template v-if="build.id">
            <a
              v-if="build.numchildren > 0"
              class="buildinfo cdash-link"
              data-test="child-builds-link"
              :href="build.multiplebuildshyperlink"
            >
              {{ build.buildname }}
            </a>
            <a
              v-else
              class="buildinfo cdash-link"
              data-test="build-summary-link"
              :href="`builds/${build.id}`"
            >
              {{ build.buildname }}
            </a>
          </template>
          <template v-else>
            {{ build.buildname }}
          </template>
        </div>

        <div style="float: left;">
          <a
            v-if="build.notes > 0"
            class="cdash-link"
            title="View notes"
            name="notesLink"
            data-test="build-notes-link"
            :href="`builds/${build.id}/notes`"
          >
            <span
              class="glyphicon glyphicon-file"
              style="color: #777; margin-right: 4px;"
            />
          </a>
          <a
            v-if="build.uploadfilecount > 0"
            class="cdash-link"
            style="float: left;"
            data-test="build-files-link"
            :href="`builds/${build.id}/files`"
            :title="`${build.uploadfilecount} files uploaded with this build`"
          >
            <span
              class="glyphicon glyphicon-compressed"
              style="color: #5cb85c; margin-right: 4px;"
            />
          </a>
          <a
            v-if="hasProblems"
            class="cdash-link"
            href="#"
            style="float: left;"
            data-test="build-problems-toggle"
            @click.prevent="toggleProblems"
          >
            <span
              class="glyphicon glyphicon-info-sign"
              style="color: #777; margin-right: 4px;"
              title="Build information"
            />
          </a>
          <a
            v-if="isMissing"
            class="cdash-link"
            href="#"
            style="float: left;"
            data-test="expected-build-info-toggle"
            @click.prevent="toggleExpectedInfo"
          >
            <span
              class="glyphicon glyphicon-info-sign"
              style="color: #777; margin-right: 4px;"
              title="Expected build information"
            />
          </a>
          <a
            v-if="build.buildnotes > 0"
            :id="`buildnote_${build.id}`"
            class="cdash-link"
            name="Build Notes"
            data-test="build-comments-link"
            :href="`builds/${build.id}/comments`"
          >
            <span
              class="glyphicon glyphicon-comment"
              style="color: #337ab7; margin-right: 4px;"
            />
          </a>
          <div
            v-if="settings.admin"
            style="float: left;"
          >
            <a
              class="cdash-link"
              href="#"
              @click.prevent="toggleAdminOptions"
            >
              <span
                name="adminoptions"
                class="glyphicon glyphicon-cog"
                style="color: #777; margin-right: 4px;"
                data-test="build-admin-options-toggle"
              />
            </a>
            <span
              v-if="loading"
              class="glyphicon glyphicon-refresh glyphicon-spin"
              style="color: #5bc0de;"
              data-test="build-loading"
            />
          </div>
        </div>

        <BuildProblems
          v-if="showProblems"
          :problems="problems"
          :project-name="settings.projectName"
        />

        <table
          v-if="showExpectedInfo"
          width="100%"
          border="0"
          data-test="expected-build-info"
        >
          <tbody>
            <tr>
              <td
                bgcolor="#DDDDDD"
                style="border-width: 0;"
              >
                <span
                  v-if="Number(expectedInfo.lastSubmission) === -1"
                  style="font-size: small;"
                >
                  This build has never submitted.
                </span>
                <span
                  v-else
                  style="font-size: small;"
                >
                  This build has not submitted since
                  <b>
                    <a
                      class="cdash-link"
                      :href="`index.php?project=${settings.projectName}&date=${expectedInfo.lastSubmissionDate}`"
                    >
                      {{ expectedInfo.lastSubmission }}
                    </a>
                    ({{ days(expectedInfo.daysSinceLastBuild) }})
                  </b>
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <div
          v-if="settings.admin"
          style="clear: both;"
        >
          <div
            v-if="showAdminOptions"
            data-test="build-admin-options"
            style="padding: 8px 0; display: flex; flex-direction: column; gap: 8px;"
          >
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
              <!-- Expected builds belong to daily groups.  Expected-and-missing rows in dynamic groups
                   can only come from old data, so they can still be cleared. -->
              <button
                v-if="group.type !== 'Latest' || isMissing"
                class="btn btn-xs"
                :class="isExpected ? 'btn-warning' : 'btn-success'"
                :disabled="updatingExpected"
                data-test="toggle-expected-button"
                @click="toggleExpected"
              >
                <span
                  class="glyphicon"
                  :class="isExpected ? 'glyphicon-remove' : 'glyphicon-ok'"
                />
                {{ isExpected ? 'Mark this build as not expected' : 'Mark this build as expected' }}
              </button>

              <!-- Expected builds which haven't submitted can only be removed by not expecting them. -->
              <button
                v-if="build.id"
                class="btn btn-xs btn-danger"
                data-test="remove-build-button"
                @click="confirmingRemoval = true"
              >
                <span class="glyphicon glyphicon-trash" />
                Remove This Build
              </button>

              <span
                v-if="build.id && !isMissing"
                title="Done builds will be overwritten if a new one is submitted with the same site, build name, and timestamp."
              >
                <button
                  class="btn btn-xs"
                  :class="done ? 'btn-default' : 'btn-info'"
                  :disabled="updatingDone"
                  data-test="toggle-done-button"
                  @click="toggleDone"
                >
                  <span
                    class="glyphicon"
                    :class="done ? 'glyphicon-ban-circle' : 'glyphicon-ok-circle'"
                  />
                  {{ done ? 'Mark as Not Done' : 'Mark as Done' }}
                </button>
              </span>
            </div>

            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px;">
              <select
                v-model="moveTargetGroup"
                style="padding: 2px 6px;"
                data-test="move-to-group-select"
              >
                <option
                  v-for="targetGroup in settings.allBuildGroups"
                  :key="targetGroup.id"
                  :value="String(targetGroup.id)"
                >
                  {{ targetGroup.name }}
                </option>
              </select>
              <button
                class="btn btn-xs btn-primary"
                :disabled="!moveTargetGroup || moveTargetGroup === String(group.id)"
                data-test="move-to-group-button"
                @click="moveToGroup"
              >
                <span class="glyphicon glyphicon-arrow-right" />
                Move to group
              </button>
            </div>
          </div>
        </div>

        <div v-if="build.changelink">
          <a
            class="cdash-link"
            target="_blank"
            rel="noopener noreferrer"
            data-test="change-link"
            :href="build.changelink"
          >
            <img
              class="smallicon"
              :src="build.changeicon"
            >
          </a>
        </div>

        <RemoveBuildDialog
          v-if="confirmingRemoval"
          @cancel="confirmingRemoval = false"
          @confirm="removeBuild"
        />
      </td>
    </template>

    <td
      v-if="group.hasupdatedata"
      align="center"
      data-test="update-files"
      :class="updateStatus"
    >
      <div v-if="build.hasupdate">
        <a
          class="cdash-link"
          :href="`builds/${build.id}/update`"
        >
          {{ build.update.files }}
        </a>
      </div>
    </td>

    <td
      v-if="group.hasupdatedata && settings.showTimeColumns"
      align="center"
      data-test="update-time"
    >
      <div v-if="build.hasupdate">
        {{ build.update.time }}
      </div>
    </td>

    <td
      v-if="group.hasconfiguredata"
      align="center"
      data-test="configure-errors"
      :class="status(build.configure?.error, 'error')"
    >
      <div v-if="build.hasconfigure">
        <a
          class="cdash-link"
          :href="`builds/${build.id}/configure`"
        >
          {{ build.configure.error }}
        </a>
      </div>
    </td>

    <td
      v-if="group.hasconfiguredata"
      align="center"
      data-test="configure-warnings"
      :class="status(build.configure?.warning, 'warning')"
    >
      <div v-if="build.hasconfigure">
        <a
          class="cdash-link"
          :href="`builds/${build.id}/configure`"
        >
          {{ build.configure.warning }}
        </a>
        <sub v-if="build.configure.warningdiff > 0">
          +{{ build.configure.warningdiff }}
        </sub>
        <!-- Starting with text keeps the space before the change, like the other changes. -->
        <sub v-if="build.configure.warningdiff < 0">
          -{{ -build.configure.warningdiff }}
        </sub>
      </div>
    </td>

    <td
      v-if="group.hasconfiguredata && settings.showTimeColumns"
      align="center"
      data-test="configure-time"
    >
      <div v-if="build.hasconfigure">
        {{ build.configure.time }}
      </div>
    </td>

    <td
      v-if="group.hascompilationdata"
      align="center"
      data-test="build-errors"
      :class="status(build.compilation?.error, 'error')"
    >
      <div
        v-if="build.hascompilation"
        :class="{ valuewithsub: build.compilation.nerrordiffp > 0 || build.compilation.nerrordiffn > 0 }"
      >
        <a
          class="cdash-link"
          :href="`builds/${build.id}/build`"
        >
          {{ build.compilation.error }}
        </a>
        <a
          v-if="build.compilation.nerrordiffp > 0"
          class="sup cdash-link"
          :href="`builds/${build.id}/build?onlydeltap`"
        >
          +{{ build.compilation.nerrordiffp }}
        </a>
        <a
          v-if="build.compilation.nerrordiffn > 0"
          class="cdash-link"
          :href="`builds/${build.id}/build?onlydeltan`"
        >
          <span class="sub">
            -{{ build.compilation.nerrordiffn }}
          </span>
        </a>
      </div>
    </td>

    <td
      v-if="group.hascompilationdata"
      align="center"
      data-test="build-warnings"
      :class="status(build.compilation?.warning, 'warning')"
    >
      <div
        v-if="build.hascompilation"
        :class="{ valuewithsub: build.compilation.nwarningdiffp > 0 || build.compilation.nwarningdiffn > 0 }"
      >
        <a
          class="cdash-link"
          :href="`builds/${build.id}/build`"
        >
          {{ build.compilation.warning }}
        </a>
        <a
          v-if="build.compilation.nwarningdiffp > 0"
          class="sup cdash-link"
          :href="`builds/${build.id}/build?onlydeltap`"
        >
          +{{ build.compilation.nwarningdiffp }}
        </a>
        <a
          v-if="build.compilation.nwarningdiffn > 0"
          class="cdash-link"
          :href="`builds/${build.id}/build?onlydeltan`"
        >
          <span class="sub">
            -{{ build.compilation.nwarningdiffn }}
          </span>
        </a>
      </div>
    </td>

    <td
      v-if="group.hascompilationdata && settings.showTimeColumns"
      align="center"
      data-test="build-time"
    >
      <div v-if="build.hascompilation">
        {{ build.compilation.time }}
      </div>
    </td>

    <td
      v-if="group.hastestdata"
      align="center"
      data-test="tests-not-run"
      :class="status(build.test?.notrun, 'warning')"
    >
      <div
        v-if="build.hastest"
        :class="{ valuewithsub: build.test.nnotrundiffp > 0 || build.test.nnotrundiffn > 0 }"
      >
        <a
          class="cdash-link"
          :href="testsUrl('status', 'NOT_RUN')"
        >
          {{ build.test.notrun }}
        </a>
        <a
          v-if="build.test.nnotrundiffp > 0"
          class="sup cdash-link"
          :href="testsUrl('status', 'NOT_RUN', true)"
        >
          +{{ build.test.nnotrundiffp }}
        </a>
        <span
          v-if="build.test.nnotrundiffn > 0"
          class="sub"
        >
          -{{ build.test.nnotrundiffn }}
        </span>
      </div>
    </td>

    <td
      v-if="group.hastestdata"
      align="center"
      data-test="tests-failed"
      :class="{ error: build.test?.fail > 0, normal: build.test?.fail < 1 }"
    >
      <div
        v-if="build.hastest"
        :class="{ valuewithsub: build.test.nfaildiffp > 0 || build.test.nfaildiffn > 0 }"
      >
        <a
          class="cdash-link"
          :href="testsUrl('status', 'FAILED')"
        >
          {{ build.test.fail }}
        </a>
        <a
          v-if="build.test.nfaildiffp > 0"
          class="sup cdash-link"
          :href="testsUrl('status', 'FAILED', true)"
        >
          +{{ build.test.nfaildiffp }}
        </a>
        <span
          v-if="build.test.nfaildiffn > 0"
          class="sub"
        >
          -{{ build.test.nfaildiffn }}
        </span>
      </div>
    </td>

    <td
      v-if="group.hastestdata"
      align="center"
      data-test="tests-passed"
      :class="{ normal: build.test?.pass > -1 }"
    >
      <div
        v-if="build.hastest"
        :class="{ valuewithsub: build.test.npassdiffp > 0 || build.test.npassdiffn > 0 }"
      >
        <a
          class="cdash-link"
          :href="testsUrl('status', 'PASSED')"
        >
          {{ build.test.pass }}
        </a>
        <a
          v-if="build.test.npassdiffp > 0"
          class="sup cdash-link"
          :href="testsUrl('status', 'PASSED', true)"
        >
          +{{ build.test.npassdiffp }}
        </a>
        <span
          v-if="build.test.npassdiffn > 0"
          class="sub"
        >
          -{{ build.test.npassdiffn }}
        </span>
      </div>
    </td>

    <td
      v-if="group.hastestdata && settings.showTestTime"
      align="center"
      data-test="test-time"
      :class="status(build.test?.timestatus, 'error')"
    >
      <div
        v-if="build.hastest"
        :class="{ valuewithsub: build.test.ntimediffp > 0 || build.test.ntimediffn > 0 }"
      >
        <!-- The number of tests which took longer than usual. -->
        <div v-if="build.test.timestatus > 0">
          <a
            class="cdash-link"
            :href="testsUrl('timeStatusCategory', 'FAILED')"
          >
            {{ build.test.timestatus }}
          </a>
          <a
            v-if="build.test.ntimediffp > 0"
            class="sup cdash-link"
            :href="testsUrl('timeStatusCategory', 'FAILED', true)"
          >
            +{{ build.test.ntimediffp }}
          </a>
          <span
            v-if="build.test.ntimediffn > 0"
            class="sub"
          >
            -{{ build.test.ntimediffn }}
          </span>
        </div>
        <span>
          {{ build.test.time }}
        </span>
      </div>
    </td>

    <td
      v-if="group.hastestdata && settings.showProcTime"
      align="center"
      data-test="proc-time"
    >
      {{ build.test?.procTime }}
    </td>

    <td
      v-if="settings.showStartTime"
      align="center"
      data-test="start-time"
    >
      <span
        v-if="!build.builddate"
        class="builddateelapsed"
        :title="`Expected submit time: ${build.expectedstarttime}`"
      >
        Expected build
      </span>
      <div v-else>
        <span
          class="builddateelapsed"
          :title="build.timesummary"
        >
          {{ build.builddateelapsed }}
        </span>
      </div>
    </td>

    <td
      v-if="settings.showOrder"
      align="center"
      data-test="order"
    >
      {{ build.position }}
    </td>

    <td
      v-if="settings.showLabels"
      class="nob"
      align="left"
      data-test="labels"
    >
      {{ build.label }}
    </td>
  </tr>
</template>

<script>
import LegacyApi from './legacy/LegacyApi';
import BuildProblems from './BuildProblems.vue';
import RemoveBuildDialog from './RemoveBuildDialog.vue';
import { pluralizeDays } from './pluralizeDays';

/**
 * A build in a build group's table, and the actions project administrators can take on it.  The
 * build is described by the legacy index API.
 */
export default {
  name: 'BuildRow',

  components: {
    BuildProblems,
    RemoveBuildDialog,
  },

  props: {
    build: {
      type: Object,
      required: true,
    },

    /** The build group, which says which sections of the table to show. */
    group: {
      type: Object,
      required: true,
    },

    /** The page-wide settings.  See ProjectBuildsPage.vue. */
    settings: {
      type: Object,
      required: true,
    },

    /** Whether the build group is in bulk selection mode. */
    selecting: {
      type: Boolean,
      default: false,
    },

    selected: {
      type: Boolean,
      default: false,
    },
  },

  emits: {
    'update:selected': (selected) => typeof selected === 'boolean',
    /** Emitted once the build has been removed, or is no longer expected. */
    removed: () => true,
    /**
     * Emitted with changes to the build's `done` and `expected` properties, which the build group
     * keeps so they aren't lost when the row is shown again on another page.
     */
    'update:build': (changes) => typeof changes === 'object',
  },

  data() {
    return {
      loading: false,
      problems: null,
      showProblems: false,
      expectedInfo: null,
      showExpectedInfo: false,
      showAdminOptions: false,
      updatingExpected: false,
      updatingDone: false,
      moveTargetGroup: String(this.group.id),
      confirmingRemoval: false,
    };
  },

  computed: {
    /** Whether the build is expected but hasn't submitted. */
    isMissing() {
      return Number(this.build.expectedandmissing) === 1;
    },

    /** Whether the build is expected to submit every day, once it's known. */
    expected() {
      return this.build.expected ?? null;
    },

    isExpected() {
      return this.isMissing || Number(this.expected) === 1;
    },

    done() {
      return Number(this.build.done) === 1;
    },

    hasProblems() {
      return this.build.compilation?.error > 0 || this.build.test?.fail > 0;
    },

    updateStatus() {
      if (!this.build.hasupdate) {
        return '';
      }
      if (Number(this.build.update.errors) === 1) {
        return 'error';
      }
      return Number(this.build.update.warning) === 1 ? 'warning' : 'normal';
    },
  },

  methods: {
    days(count) {
      return pluralizeDays(count);
    },

    /**
     * The class for a count of problems, which is normal if there are none.
     */
    status(count, problemClass) {
      if (count > 0) {
        return problemClass;
      }
      return Number(count) === 0 ? 'normal' : '';
    },

    /**
     * A link to the build's tests with a status.
     *
     * @param {string} field 'status' or 'timeStatusCategory'.
     * @param {string} value e.g. 'FAILED'.
     * @param {boolean} onlyDelta Whether to only show the tests whose status changed.
     */
    testsUrl(field, value, onlyDelta = false) {
      const filters = encodeURIComponent(JSON.stringify({ all: [{ eq: { [field]: value } }] }));
      return `builds/${this.build.id}/tests?${onlyDelta ? 'onlydelta&' : ''}filters=${filters}${this.settings.subprojectTestsQuery}`;
    },

    async toggleProblems() {
      if (this.problems === null) {
        this.loading = true;
        try {
          this.problems = await LegacyApi.buildProblems(this.build.id);
        } finally {
          this.loading = false;
        }
        this.showProblems = true;
      } else {
        this.showProblems = !this.showProblems;
      }
    },

    async toggleExpectedInfo() {
      if (this.expectedInfo === null) {
        this.loading = true;
        try {
          this.expectedInfo = await LegacyApi.expectedBuildInfo({
            siteid: this.build.siteid,
            groupid: this.build.buildgroupid,
            name: this.build.buildname,
            type: this.build.buildtype,
            currenttime: this.settings.unixTimestamp,
          });
        } finally {
          this.loading = false;
        }
        this.showExpectedInfo = true;
      } else {
        this.showExpectedInfo = !this.showExpectedInfo;
      }
    },

    async toggleAdminOptions() {
      // Find out whether the build is expected before showing the button which changes it.
      if (!this.isMissing && this.expected !== 0 && this.expected !== 1) {
        this.loading = true;
        try {
          this.$emit('update:build', { expected: await LegacyApi.isBuildExpected(this.build.id) });
        } finally {
          this.loading = false;
        }
      }
      this.showAdminOptions = !this.showAdminOptions;
    },

    async toggleExpected() {
      this.updatingExpected = true;
      try {
        if (this.isMissing) {
          await LegacyApi.deleteExpectedBuild({
            siteid: this.build.siteid,
            groupid: this.build.buildgroupid,
            name: this.build.buildname,
            type: this.build.buildtype,
          });
          this.$emit('removed');
        } else {
          const expected = this.expected === 1 ? 0 : 1;
          await LegacyApi.setBuildExpected(this.build.id, this.group.id, expected);
          this.$emit('update:build', { expected });
        }
      } finally {
        this.updatingExpected = false;
      }
    },

    async toggleDone() {
      this.updatingDone = true;
      try {
        const done = this.done ? 0 : 1;
        await LegacyApi.setBuildDone(this.build.id, done);
        this.$emit('update:build', { done });
      } finally {
        this.updatingDone = false;
      }
    },

    async removeBuild() {
      this.confirmingRemoval = false;
      await LegacyApi.deleteBuild(this.build.id);
      this.$emit('removed');
    },

    async moveToGroup() {
      const newgroupid = Number(this.moveTargetGroup);
      if (!newgroupid || newgroupid === Number(this.group.id)) {
        return;
      }

      try {
        if (this.isMissing) {
          await LegacyApi.moveExpectedBuild({
            siteid: this.build.siteid,
            groupid: this.build.buildgroupid,
            newgroupid,
            name: this.build.buildname,
            type: this.build.buildtype,
          });
        } else {
          // Keep whether the build is expected.
          await LegacyApi.moveBuild(this.build.id, newgroupid, this.expected || 0);
        }
        window.location.reload();
      } catch (error) {
        console.error('Error moving build:', error);
        alert(`Error moving build: ${error.data?.error ?? 'Unknown error'}`);
      }
    },
  },
};
</script>
