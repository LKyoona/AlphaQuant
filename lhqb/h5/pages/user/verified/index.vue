<template>
  <div>
    <van-nav-bar
      :title="$t('pageUser.safety_lv')"
      left-arrow
      @click-left="$router.back()"
      @click-right="showPwd = true"
    />
    <van-steps direction="vertical" :active="userInfo.auth_id-1">
      <van-step v-for="item in authInfo" :key="item.id">
        <div @click="goWhere(item.id)">
          <div class="title">{{ item.name }}</div>
          <div class="content" v-html="item.content"></div>
        </div>
      </van-step>
    </van-steps>
  </div>
</template>

<script>
import { Step, Steps } from 'vant'
import { mapActions, mapState } from 'vuex'
export default {
  components: {
    [Step.name]: Step,
    [Steps.name]: Steps
  },
  computed: {
    ...mapState({
      userInfo: ({ user }) => user.userInfo,
      authInfo: ({ user }) => user.authInfo
    })
  },
  mounted () {
    this.getAuth()
  },
  methods: {
    ...mapActions({
      getAuth: 'user/getAuth',
      showAuth: 'user/showAuth'
    }),
    goWhere (index) {
      switch (index) {
        case 2:
          if (this.userInfo.auth_id === 1) { this.$toast(this.$t('pageVerified.no_pwd')) }
          break
        case 3:
          if (this.userInfo.auth_id === 2) {
            this.$toast.loading()
            this.showAuth().then((res) => {
              if (res.data.status === 2) {
                this.$toast(this.$t('pageVerified.during'))
              } else if (res.data.status === 0) {
                this.$router.push('/user/verified/form')
              } else if (res.data.status === -1) {
                this.$toast(this.$t('pageVerified.fail'))
                this.$router.push('/user/verified/form')
              }
            })
          }
          break
        default:
          break
      }
    }
  }
}
</script>

<style scoped lang="less">
.title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 10px;
}
</style>
