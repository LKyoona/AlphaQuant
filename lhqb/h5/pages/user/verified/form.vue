<template>
  <div>
    <van-nav-bar
      :title="$t('pageVerifiedForm.title')"
      left-arrow
      @click-left="$router.back()"
      @click-right="showPwd = true"
    />
    <van-form @submit="onSubmit">
      <van-field
        v-model="form.country"
        :label="$t('pageVerifiedForm.nationality')"
        :placeholder="$t('pageVerifiedForm.nationality_please')"
        :rules="[{ required: true, message: $t('pageVerifiedForm.nationality_please') }]"
      />
      <van-field
        v-model="form.address"
        :label="$t('pageVerifiedForm.address')"
        :placeholder="$t('pageVerifiedForm.address_please')"
        :rules="[{ required: true, message: $t('pageVerifiedForm.address_please') }]"
      />
      <van-field
        v-model="form.u_name"
        :label="$t('pageVerifiedForm.name')"
        :placeholder="$t('pageVerifiedForm.name_please')"
        :rules="[{ required: true, message: $t('pageVerifiedForm.name_please') }]"
      />
      <van-field
        :value="form.birthday"
        :label="$t('pageVerifiedForm.birthday')"
        :placeholder="$t('pageVerifiedForm.birthday_please')"
        readonly
        clickable
        :rules="[{ required: true, message: $t('pageVerifiedForm.birthday_please') }]"
        @click="showPicker = true"
      />
      <van-field
        v-model="form.photo_id"
        :label="$t('pageVerifiedForm.card')"
        :placeholder="$t('pageVerifiedForm.card_please')"
        :rules="[{ required: true, message: $t('pageVerifiedForm.card_please') }]"
      />
      <van-field :label="$t('pageVerifiedForm.card_front')">
        <template #input>
          <van-uploader name="photo_id1" :after-read="afterRead">
            <van-image height="38vw" :src="card_frontend"></van-image>
            <van-image
              v-if="form.photo_id1"
              fit="cover"
              class="preview-cover"
              :src="IMG_BASE_URL + form.photo_id1"
            ></van-image>
          </van-uploader>
        </template>
      </van-field>
      <van-field :label="$t('pageVerifiedForm.card_back')">
        <template #input>
          <van-uploader name="photo_id2" :after-read="afterRead">
            <van-image height="38vw" :src="card_backend"></van-image>
            <van-image
              v-if="form.photo_id2"
              fit="cover"
              class="preview-cover"
              :src="IMG_BASE_URL + form.photo_id2"
            ></van-image>
          </van-uploader>
        </template>
      </van-field>
      <div style="padding: 16px">
        <van-button round block type="info" native-type="submit">{{ $t('actions.submit') }}</van-button>
      </div>
    </van-form>
    <van-popup v-model:show="showPicker" position="bottom">
      <van-date-picker
        type="date"
        :min-date="minDate"
        :max-date="maxDate"
        @confirm="onConfirm"
        @cancel="showPicker = false"
      />
    </van-popup>
  </div>
</template>

<script>
import { Uploader, DatePicker } from 'vant'
import { mapActions } from 'vuex'
import { format as timeFormat } from '@/utils/time'
import { IMG_BASE_URL } from '@/config/index'
import cardFrontend from '@/assets/images/card-frontend.svg'
import cardBackend from '@/assets/images/card-backend.svg'
export default {
  components: {
    [Uploader.name]: Uploader,
    [DatePicker.name]: DatePicker
  },
  data () {
    return {
      IMG_BASE_URL,
      card_frontend: cardFrontend,
      card_backend: cardBackend,
      showPicker: false,
      minDate: new Date(1970, 0, 1),
      maxDate: new Date(),
      form: {
        country: '',
        u_name: '',
        birthday: '',
        photo_id1: '',
        photo_id2: '',
        photo_id: '',
        address: ''
      }
    }
  },
  methods: {
    ...mapActions({
      addAuth: 'user/addAuth',
      upload: 'upload'
    }),
    onSubmit () {
      if (!this.form.photo_id1 || !this.form.photo_id2) {
        this.$toast.fail(this.$t('pageVerified.upload_id'))
        return
      }
      this.$toast.loading()
      this.addAuth({ ...this.form }).then((res) => {
        this.$toast(res.msg)
        this.$router.back()
      }).catch((res) => { this.$toast(res.msg) })
    },
    onConfirm (value) {
      this.form.birthday = timeFormat(value, '{y}-{m}-{d}')
      this.showPicker = false
    },
    afterRead (file, detail) {
      const toast = this.$toast.loading()
      this.upload(file.file)
        .then((res) => {
          this.form[detail.name] = res.data.url
        })
        .finally(() => {
          toast.clear()
        })
    }
  }
}
</script>

<style scoped lang="less">
.preview-cover {
  position: absolute;
  bottom: 0;
  left: 0;
  box-sizing: border-box;
  width: 100%;
  height: 100%;
}
</style>
