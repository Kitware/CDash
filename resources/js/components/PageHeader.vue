<template>
    <div>
        <HeaderTop :user="user" :menu="menu" :pathname="pathname"></HeaderTop>
        <HeaderBottom></HeaderBottom>
    </div>
</template>

<script>
    import HeaderTop from "./page-header/HeaderTop";
    import HeaderBottom from "./page-header/HeaderBottom";
    export default {
        name: "Header",
        components: {HeaderBottom, HeaderTop},
        props: ['menu', 'pathname', 'user']
    }
</script>

<style scoped>

</style>
