<template>
  <table
    width="100%"
    border="0"
    data-test="build-problems"
  >
    <tbody>
      <tr
        v-if="problems.hasErrors"
        data-test="build-failing-since"
      >
        <td
          bgcolor="#DDDDDD"
          style="border-width: 0;"
        >
          <span style="font-size: small;">
            Build has been failing since
            <b>
              <a
                class="cdash-link"
                :href="`index.php?project=${projectName}&date=${problems.failingDate}`"
              >
                {{ problems.failingSince }}
              </a>
              ({{ pluralizeDays(problems.daysWithErrors, 'today') }})
            </b>
          </span>
        </td>
      </tr>
      <tr
        v-if="problems.hasFailingTests"
        data-test="tests-failing-since"
      >
        <td
          bgcolor="#DDDDDD"
          style="border-width: 0;"
        >
          <span style="font-size: small;">
            Tests have been failing since
            <b>
              <a
                class="cdash-link"
                :href="`index.php?project=${projectName}&date=${problems.testsFailingDate}`"
              >
                {{ problems.testsFailingSince }}
              </a>
              ({{ pluralizeDays(problems.daysWithFailingTests, 'today') }})
            </b>
          </span>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script>
import { pluralizeDays } from './pluralizeDays';

/**
 * When a build's errors and failing tests started, according to the legacy build API.
 */
export default {
  name: 'BuildProblems',

  props: {
    problems: {
      type: Object,
      required: true,
    },

    projectName: {
      type: String,
      required: true,
    },
  },

  setup() {
    return { pluralizeDays };
  },
};
</script>
