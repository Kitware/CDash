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
        >
      </a>
      <span id="footertext">
        CDash
        {{ version }} © <a href="http://www.kitware.com">Kitware</a>
        | <a
          href="https://github.com/Kitware/CDash/issues"
          target="blank"
        >Report problems</a> |
        <a :href="endpoint">View as JSON</a>
        <span v-if="generationtime">
          | {{ generationtime }}s
        </span>
      </span>
    </div>
  </div>
</template>

<script>
export default {
  name: "PageFooter",

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
      endpoint: null,
      generationtime: null,
    }
  },

  mounted() {
    this.$root.$on('api-loaded', cdash => {
      if (cdash.generationtime) {
        this.generationtime = cdash.generationtime;
      }
      if (cdash.endpoint) {
        this.endpoint = cdash.endpoint;
      }
    });
  },
}
</script>
