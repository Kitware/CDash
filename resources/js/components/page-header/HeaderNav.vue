<template>
  <ul
    v-if="showNav"
    class="projectnav_controls clearfix"
  >
    <li
      id="header-nav-previous-btn"
      :class="previousClass"
    >
      <a :href="previous">
        <svg
          id="i-chevron-left"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 32 32"
          width="8"
          height="8"
          fill="none"
          stroke="currentcolor"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="4"
        >
          <path d="M20 30 L8 16 20 2" />
        </svg>
        PREV
      </a>
    </li>
    <li
      id="header-nav-current-btn"
      :class="currentClass"
    >
      <a :href="current">LATEST</a>
    </li>
    <li
      id="header-nav-next-btn"
      :class="nextClass"
    >
      <a :href="next">
        NEXT
        <svg
          id="i-chevron-right"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 32 32"
          width="8"
          height="8"
          fill="none"
          stroke="currentcolor"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="4"
        >
          <path d="M12 30 L24 16 12 2" />
        </svg>
      </a>
    </li>
  </ul>
</template>

<script>
import ApiLoader from '../shared/ApiLoader';
export default {
  name: "HeaderNav",

  data() {
    return {
      previous: null,
      current: null,
      next: null,
      showNav: false,
    }
  },

  computed: {
    previousClass () {
      return this.previous === null ? 'btn-disabled' : 'btn-enabled';
    },

    currentClass () {
      return this.current === null ? 'btn-disabled' : 'btn-enabled';
    },

    nextClass () {
      return this.next === null ? 'btn-disabled' : 'btn-enabled';
    }
  },

  mounted() {
    ApiLoader.$on('api-loaded', cdash => {
      if (!cdash.menu) {
        return;
      }

      if (cdash.menu.previous) {
        this.previous = this.$baseURL + cdash.menu.previous;
        this.showNav = true;
      }
      if (cdash.menu.current) {
        this.current = this.$baseURL + cdash.menu.current;
        this.showNav = true;
      }
      if (cdash.menu.next) {
        this.next = this.$baseURL + cdash.menu.next;
        this.showNav = true;
      }
    });
  },

}
</script>

<style scoped>
    nav {
        display: block;
        margin: 0 auto;
        padding: 19px;
    }

    .projectnav_controls {
        border-radius: 3px;
        display: inline-block;
        list-style: none;
        margin: 0;
        overflow: hidden;
        padding: 0;
    }


    .btn-enabled {
        background: #555;
        border: 1px solid #212121;
        font-size: 11px;
        margin-right: -1px;
        text-transform: uppercase;
        float: left;
        cursor: pointer;
    }

    .btn-disabled {
        background: #444 !important; /* TODO: (williamjallen) find a cleaner way to set the background */
        border: 1px solid #212121;
        font-size: 11px;
        margin-right: -1px;
        text-transform: uppercase;
        float: left;
        cursor: default;
    }

    .btn-disabled a {
        color: rgba(255, 255, 255, 0.5) !important;
    }

    #header-nav-previous-btn {
        border-right: 1px solid #212121;
        text-align: left;
    }

    #header-nav-current-btn {
        border-left: 1px solid #999;
        border-right: 1px solid #212121;
        text-align: center;
    }

    #header-nav-next-btn {
        border-left: 1px solid #999;
        text-align: right;
    }

    a {
        color: white;
        display: block;
        font-size: 11px;
        padding: 5px 12px 3px;
        height: 100%;
        text-decoration: none;
        width: 100%;
    }
</style>
