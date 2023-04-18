<template>
  <div
    id="footer"
    class="clearfix"
  >
    <div id="kitwarelogo">
      <a href="http://www.kitware.com">
        <img
          :src="kitwareLogo"
          border="0"
          alt="logo"
        >
      </a>
    </div>
    <div
      id="footerlinks"
      class="clearfix"
    >
      <a
        href="http://www.cdash.org"
        class="footerlogo"
      >
        <img
          :src="cdashLogo"
          border="0"
          height="30"
          alt="CDash logo"
          class="float-left"
        >
      </a>
      <span
        id="footertext"
        class="float-right"
      >
        CDash
        {{ version }} © <a href="http://www.kitware.com">Kitware</a>
        | <a
          href="https://github.com/Kitware/CDash/issues"
          target="blank"
        >Report problems</a> |
        <a :href="endpoint">View as JSON</a>
        <span
          v-if="generationtime"
          v-tooltip.top-start="{
            content: 'Total (Backend API)',
            delay: 1500,
          }"
        >
          | {{ generationtime }}
        </span>
        <span v-if="currentdate">
          <br>
          Current Testing Day {{ currentdate }} | Started at {{ nightlytime }}
        </span>
      </span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PageFooter',

  props: {
    cdashLogo: {
      type: String,
      default: '',
    },
    kitwareLogo: {
      type: String,
      default: '',
    },
    version: {
      type: String,
      default: '',
    },
  },

  data() {
    return {
      currentdate: null,
      endpoint: null,
      generationtime: null,
      nightlytime: null,
    };
  },

  mounted() {
    this.$root.$on('api-loaded', cdash => {
      if (cdash.generationtime) {
        this.generationtime = cdash.generationtime;
      }
      if (cdash.endpoint) {
        this.endpoint = cdash.endpoint;
      }
      if (cdash.currentdate) {
        this.currentdate = cdash.currentdate;
      }
      if (cdash.nightlytime) {
        this.nightlytime = cdash.nightlytime;
      }
    });
  },
};
</script>
