<template>
  <div class="settings-page">
    <van-nav-bar :title="$t('changeLang')" left-arrow @click-left="$router.back()" />
    <van-cell-group>
      <van-cell v-for="(item, index) in langs" :key="item" @click="changeFn(index)">
        <span>{{ item }}</span>
        <van-icon v-if="locale === index" name="success" color="@themeColor" />
      </van-cell>
    </van-cell-group>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex'
export default {
  data () {
    return {
      langs: {
        zh: '中文',
        en: 'English'
      }
    }
  },
  computed: {
    ...mapState({
      locale: state => state.locale
    })
  },
  methods: {
    ...mapActions({
      setLang: 'setLang'
    }),
    changeFn (index) {
      this.$i18n.locale = index
      if (this.$root && this.$root.$i18n) {
        this.$root.$i18n.locale = index
      }
      this.setLang(index)
      this.$nextTick(() => {
        this.$forceUpdate()
      })
      // location.pathname = '/'
    }
  }
}
</script>

<style scoped lang="less">
.van-cell__value {
  display: flex;align-items: center;justify-content: space-between;
}
</style>
