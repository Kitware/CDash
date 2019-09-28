<template>
    <nav id="headermenu">
        <ul id="navigation">
            <li v-for="menu in menus">
                <a :href="menu.path + buildQueryString(menu.query)">{{ menu.text }}</a>
                <ul v-if="menu.items.length">
                    <li v-for="item in menu.items">
                        <a v-if="item.path" :href="item.path + buildQueryString(item.query)">{{ item.text }}</a>
                        <a v-else href="#" @click="[item.fn]">{{ item.text }}</a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
</template>

<script>
    import moment from 'moment';

    export default {
        name: "HeaderMenu",
        props: ['project', 'projectId', 'homeUrl', 'docUrl', 'bugUrl', 'vcsUrl', 'today'],
        data () {
            return {
                menus: [
                    {
                        text: 'Dashboard',
                        fn: null,
                        path: 'index.php',
                        query: {project: this.project, date: this.today},
                        items: [
                            {
                                text: 'Overview',
                                path: 'overview.php',
                                query: {project: this.project, date: this.today},
                            },
                            {
                                text: 'Builds',
                                path: 'buildOverview.php',
                                query: {project: this.project, date: this.today},
                            },
                            {
                                text: 'Tests',
                                path: 'testOverview.php',
                                query: {project: this.project, date: this.today},
                            },
                            {
                                text: 'Test Query',
                                path: 'queryTests.php',
                                query: {project: this.project, date: this.today},
                            },
                            {
                                text: 'Statistics',
                                path: 'userStatistics.php',
                                query: {project: this.project, date: this.today},
                            },
                            {
                                text: 'Sites',
                                path: 'viewMap.php',
                                query: {project: this.project, date: this.today},
                            }
                        ]
                    },
                    {
                        text: 'Calendar',
                        fn: 'showCalendar',
                        path: '#',
                        items: [],
                        query: {}
                    },
                    {
                        text: 'Project',
                        path: 'index.php',
                        query: {project: this.project},
                        fn: null,
                        items: [
                            {
                                text: 'Home',
                                path: this.homeUrl,
                                query: {},
                            },
                            {
                                text: 'Documentation',
                                path: this.docUrl,
                                query: {},
                            },
                            {
                                text: 'Repository',
                                path: this.vcsUrl,
                                query: {},
                            },
                            {
                                text: 'Bug Tracker',
                                path: this.bugUrl,
                                query: {},
                            },
                            {
                                text: 'Subscribe',
                                path: 'subscribeProject.php',
                                query: {projectid: this.projectId},
                            }
                        ]
                    }
                ],
            }
        },

        methods: {
           buildQueryString (query) {
               const queryString = [];
               Object.keys(query).forEach((key) => {
                   queryString.push(`${key}=${query[key]}`);
               });

               return queryString.length ? '?' + queryString.join('&') : '';
            },

            showCalendar () {
                alert('Hi');
            }
        },

        computed: {
            date () {
                return moment().format('YYYY-MM-DD');
            }
        }
    }
</script>

<style scoped>
    nav {
        display: block;
        float: right;
        height: 100%;
        order: 4;
    }
</style>
