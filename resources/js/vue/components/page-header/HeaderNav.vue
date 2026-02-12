<template>
  <ul class="projectnav_controls clearfix">
    <li
      id="header-nav-previous-btn"
      :class="previousClass"
    >
      <a
        class="cdash-link"
        :href="previousUrl"
      >
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
      :class="latestClass"
    >
      <a
        class="cdash-link"
        :href="latestUrl"
      >LATEST</a>
    </li>
    <li
      id="header-nav-next-btn"
      :class="nextClass"
    >
      <a
        class="cdash-link"
        :href="nextUrl"
      >
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
export default {
  name: 'HeaderNav',

  props: {
    previousUrl: {
      type: [String, null],
      default: null,
    },

    latestUrl: {
      type: [String, null],
      default: null,
    },

    nextUrl: {
      type: [String, null],
      default: null,
    },
  },

  computed: {
    previousClass () {
      return this.previousUrl === null ? 'btn-disabled' : 'btn-enabled';
    },

    latestClass () {
      return this.latestUrl === null ? 'btn-disabled' : 'btn-enabled';
    },

    nextClass () {
      return this.nextUrl === null ? 'btn-disabled' : 'btn-enabled';
    },
  },
};
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

    svg {
      display: inline-block;
      vertical-align: middle;
    }
</style>
