<template>
  <div data-test="parent-build-info">
    <br>
    <div
      id="site"
      align="left"
      data-test="parent-site"
    >
      <b>Site</b>:
      <a
        class="cdash-link"
        :href="`sites/${cdash.siteid}?project=${cdash.projectid}&currenttime=${cdash.unixtimestamp}`"
      >
        {{ cdash.site }}
      </a>
      <!-- Margins stand in for the spaces between elements which Vue's templates leave out. -->
      <img
        v-if="Number(cdash.siteoutoforder) === 1"
        style="margin-left: 0.25em;"
        src="img/flag.png"
        title="flag"
      >
    </div>

    <div
      id="buildname"
      align="left"
      data-test="parent-build-name"
    >
      <b>Build Name</b>: {{ cdash.buildname }}
      <img
        v-if="cdash.buildplatform"
        class="icon"
        alt="platform"
        :src="`img/platform_${cdash.buildplatform}.png`"
      >
      <a
        v-if="cdash.parenthasnotes"
        class="cdash-link"
        style="margin-left: 0.25em;"
        title="View notes"
        :href="`builds/${cdash.parentid}/notes`"
      >
        <img
          src="img/document.png"
          alt="Notes"
          class="icon"
        >
      </a>
      <a
        v-if="cdash.uploadfilecount > 0"
        class="cdash-link"
        style="margin-left: 0.25em;"
        :href="`builds/${cdash.parentid}/files`"
        :title="`${cdash.uploadfilecount} files uploaded with this build`"
      >
        <img
          src="img/package.png"
          alt="Files"
          class="icon"
        >
      </a>
      <a
        v-if="cdash.changelink"
        class="cdash-link"
        style="margin-left: 0.25em;"
        target="_blank"
        rel="noopener noreferrer"
        data-test="change-link"
        :href="cdash.changelink"
      >
        <img
          class="smallicon"
          :src="cdash.changeicon"
        >
      </a>
    </div>

    <div
      id="numbuilds"
      align="left"
      data-test="parent-num-subprojects"
    >
      <b>Number of SubProjects</b>: {{ cdash.numchildren }}
    </div>

    <div v-if="advancedView">
      <div
        v-for="detail in details"
        :id="detail.id"
        :key="detail.id"
        align="left"
        :data-test="`parent-${detail.name}`"
      >
        <b>{{ detail.title }}</b>{{ detail.boldColon ? ' ' : ': ' }}{{ detail.value }}
      </div>
    </div>

    <br>
  </div>
</template>

<script>
/**
 * The site, build name and other details which a parent build's children share.  From the legacy
 * index API's response for a parent build.
 */
export default {
  name: 'ParentBuildInfo',

  props: {
    cdash: {
      type: Object,
      required: true,
    },

    advancedView: {
      type: Boolean,
      required: true,
    },
  },

  computed: {
    details() {
      const cdash = this.cdash;
      const details = [
        ['buildstamp', 'stamp', 'Stamp', cdash.stamp],
        ['buildstarttime', 'start-time', 'Build Start Time', cdash.starttime],
        ['osname', 'os-name', 'OS Name', cdash.osname],
        ['osplatform', 'os-platform', 'OS Platform', cdash.osplatform],
        ['osrelease', 'os-release', 'OS Release', cdash.osrelease],
        ['osversion', 'os-version', 'OS Version', cdash.osversion],
        ['generator', 'generator', 'CTest version:', cdash.generator],
      ];
      // The steps' durations are the same for all the children of builds which built all their
      // SubProjects at once.
      if (cdash.showorder) {
        const durations = [
          ['updateduration', 'update-time', 'Update Time:', cdash.updateduration],
          ['configureduration', 'configure-time', 'Configure Time:', cdash.configureduration],
          ['buildduration', 'build-time', 'Build Time:', cdash.buildduration],
          ['testduration', 'test-time', 'Test Time:', cdash.testduration],
        ];
        details.push(...durations.filter(([, , , duration]) => duration));
      }
      return details.map(([id, name, title, value]) => ({ id, name, title, value, boldColon: title.endsWith(':') }));
    },
  },
};
</script>
