<template>
    <div>
        <HeaderTop :user="user" :menu="menu"></HeaderTop>
        <HeaderBottom></HeaderBottom>
    </div>
</template>

<script>
    import HeaderTop from "./page-header/HeaderTop";
    import HeaderBottom from "./page-header/HeaderBottom";
    export default {
        name: "Header",
        components: {HeaderBottom, HeaderTop},
        props: ['menu', 'user']
    }
</script>

<style scoped>

</style>
