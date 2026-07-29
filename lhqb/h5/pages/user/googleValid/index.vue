<template>
  <div>
    <van-nav-bar
      :title="$t('pageUser.google_valid')"
      left-arrow
      @click-left="$router.back()"
    />
    <div class="container">
      <div v-if="step === 0">
        <van-button
          v-if="!userInfo.is_google_check"
          block
          type="primary"
          class="kaiqi"
          @click="handleClick"
        >{{ $t('pageGoogleValid.open') }}</van-button>
        <van-button
          v-else
          block
          type="primary"
          class="kaiqi"
          @click="handleClose"
        >{{ $t('pageGoogleValid.close') }}</van-button>
        <p>
          {{ $t('pageGoogleValid.tip1') }}
        </p>
        <a
          class="how"
          @click="goDetail"
        >{{ $t('pageGoogleValid.help') }}？</a>
      </div>
      <div
        v-else
        class="google"
      >
        <div class="qr">
          <qrcode-vue
            v-if="googleInfo.vcode"
            :value="googleInfo.vcode"
            size="160"
          ></qrcode-vue>
        </div>
        <div class="fuzhi">
          <a href="#">{{ googleInfo.key }}</a>
        </div>
        <div class="tixing">{{ $t('pageGoogleValid.tip2') }}</div>
        <van-button
          size="small"
          type="primary"
          @click="showPop2 = true"
        >
          {{ $t('pageGoogleValid.btn') }}
        </van-button>
      </div>
    </div>
    <van-dialog
      v-model="showPop"
      :title="$t('pageGoogleValid.set_code')"
      show-cancel-button
      @confirm="handleSubmit"
    >
      <van-cell-group>
        <van-field
          v-model="vcode"
          input-align="center"
          :placeholder="$t('pageGoogleValid.code_please')"
        />
      </van-cell-group>
    </van-dialog>
    <van-dialog
      v-model="showPop2"
      :title="$t('pageGoogleValid.google')"
      show-cancel-button
      @confirm="checkCode"
    >
      <van-cell-group>
        <van-field
          v-model="check_num"
          input-align="center"
          :placeholder="$t('pageGoogleValid.code_please')"
        />
      </van-cell-group>
    </van-dialog>
  </div>
</template>

<script>
import QrcodeVue from 'qrcode.vue'
import { mapState, mapActions } from 'vuex'
export default {
  components: { QrcodeVue },
  data () {
    return {
      step: 0,
      showPop: false,
      showPop2: false,
      vcode: '',
      check_num: '',
      googleInfo: {
        key: '',
        vcode: ''
      }
    }
  },
  computed: {
    ...mapState({
      userInfo: ({ user }) => user.userInfo,
      googleUrl: index => index.initInfo.google_auth_help
    })
  },
  methods: {
    ...mapActions({
      getUserInfo: 'user/getUserInfo',
      getCode: 'user/getCode',
      checkOpenGoogle: 'user/checkOpenGoogle',
      checkConfirmGoogle: 'user/checkConfirmGoogle',
      checkCloseGoogle: 'user/checkCloseGoogle'
    }),
    handleClick () {
      this.$toast.loading()
      this.getCode(this.userInfo.mobile)
        .then((res) => {
          this.$toast(res.msg)
          this.showPop = true
        })
        .catch((res) => {
          this.$toast(res.msg)
        })
    },
    handleSubmit () {
      this.checkOpenGoogle({ vcode: this.vcode })
        .then((res) => {
          const vcode = decodeURIComponent(res.data.vcode_img_str)
          this.googleInfo = {
            key: res.data.key,
            vcode
          }
          this.step = 1
        })
        .catch((res) => {
          this.$toast(res.msg)
        })
    },
    checkCode () {
      this.$toast.loading()
      const payload = {
        check_num: this.check_num
      }
      const promise = !this.userInfo.is_google_check ? this.checkConfirmGoogle(payload) : this.checkCloseGoogle(payload)
      promise.then((res) => {
        this.$toast(res.msg)
        this.getUserInfo()
        this.$router.back()
      })
        .catch((res) => {
          this.$toast(res.msg)
        })
    },
    handleClose () {
      this.showPop2 = true
    },
    goDetail () {
      const target = this.googleUrl
      if (!target) {
        return
      }
      if (/^https?:\/\//i.test(target)) {
        window.location.href = target
        return
      }
      this.$router.push(target)
    }
  }
}
</script>

<style scoped lang="less">
p {
  margin: 20px 0;
  color: rgba(240, 227, 197, 0.68);
}
a {
  color: #f0c46e;
}
:deep(.van-dialog__content){
  padding: 30px 15px;
}
.container {
  padding: 20px;
  color: #f0e3c5;
}
.google {
  text-align: center;
  .qr {
    margin: 0 auto 20px;
    background:
      radial-gradient(circle at top right, rgba(248, 215, 144, 0.14), transparent 30%),
      linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
    width: 200px;
    height: 200px;
    border: 1px solid rgba(217, 176, 92, 0.16);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.22);
  }
  .fuzhi {
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 20px;
    color: #fff1cf;
  }
  .tixing {
    margin: 20px;
    color: rgba(240, 227, 197, 0.68);
  }
}
</style>
