<template>
  <div class="authorize-future-page">
    <van-nav-bar
      :title="$t('pageAuthorizeFuture.title')"
      left-arrow
      @click-left="$router.back()"
    />
    <div class="list">
      <van-cell
        v-for="(item, index) in platform"
        :key="item.name"
        @click="item.disabledEdit ? () => {} : openSheet(index)"
      >
        <van-row
          type="flex"
          align="center"
          justify="space-between"
          class="item"
        >
          <van-col>
            <van-image
              class="logo"
              :class="{[item.label]: true}"
              :src="item.logo"
            />
          </van-col>
          <van-col style="flex: 1">
            <div class="name">{{ $t(item.label) }}</div>
          </van-col>
          <van-col>
            <van-icon
              v-if="!item.disabledEdit"
              style="vertical-align: middle"
              name="ellipsis"
              size="20"
            />
          </van-col>
        </van-row>
      </van-cell>
    </div>
    <van-action-sheet
      v-model="show"
      :cancel-text="$t('actions.cancel')"
    >
      <div class="sheet-title">
        <van-image
          class="logo"
          :src="currentPlatform ? currentPlatform.logo : ''"
        />
        <span>{{ currentPlatform ? $t(currentPlatform.label) : '-' }}</span>
        <span v-if="currentPlatform && !currentPlatform.api_key" class="tip">{{ $t('pageAuthorizeFuture.not') }}</span>
        <span v-if="currentPlatform && currentPlatform.status === -1" class="tip error">{{ $t('pageAuthorizeFuture.expired') }}</span>
      </div>
      <van-cell-group>
        <template v-if="currentPlatform && currentPlatform.api_key">
          <van-cell clickable is-link @click.stop="viewApi">{{ $t('actions.view') }}</van-cell>
          <van-cell clickable is-link @click.stop="openForm(active)">{{ $t('actions.edit') }}</van-cell>
          <van-cell clickable is-link @click.stop="removeApi">{{ $t('actions.del') }}</van-cell>
        </template>
        <template v-else>
          <van-cell clickable is-link @click.stop="openForm(active)">{{ $t('pageAuthorizeFuture.add') }}</van-cell>
        </template>
      </van-cell-group>
    </van-action-sheet>

    <van-popup
      v-model="showApi"
      class="api-popup"
      @close="closeApiPop"
    >
      <div class="sheet-title">
        <van-image
          class="logo"
          :src="currentPlatform ? currentPlatform.logo : ''"
        />
        <span>{{ currentPlatform ? currentPlatform.name : '-' }}</span>
        <span
          v-if="currentPlatform && currentPlatform.status === -1"
          class="tip error"
        >{{ $t('pageAuthorizeFuture.expired') }}</span>
      </div>
      <div class="api">{{ api }}</div>
    </van-popup>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex'
export default {
  data () {
    return {
      show: false,
      showApi: false,
      api: '',
      active: 0,
      actions: [{ name: '查看API Key' }, { name: '删除' }, { name: '重置' }]
    }
  },
  computed: {
    ...mapState({
      platform: ({ authorize }) => (authorize.platform || []).filter(item => item.supportsFuture)
    }),
    currentPlatform () {
      return this.platform && this.platform[this.active] ? this.platform[this.active] : null
    }
  },
  mounted () {
    this.getData()
  },
  activated () {
    this.getData()
  },
  methods: {
    ...mapActions({
      getApiAccount: 'authorize/getFutureApiAccount',
      removeApiAccount: 'authorize/removeApiAccount',
      setApiInfo: 'authorize/setApiInfo'
    }),
    getData() {
      (this.platform || []).forEach((item, index) => {
        if (item.disabledEdit) {
          return
        }
        this.getApiAccount({ platform: item.label }).then((res) => {
          this.setApiInfo([index, res.data])
        }).catch(() => { })
      })
    },
    openSheet (index) {
      this.active = index
      this.show = true
    },
    openForm (index) {
      this.show = false
      this.$router.push('/authorizeFuture/form?active=' + index)
    },
    viewApi () {
      this.show = false
      this.api = this.currentPlatform ? this.currentPlatform.api_key : ''
      this.showApi = true
    },
    closeApiPop () {
      this.showApi = false
      this.$nextTick(() => {
        this.api = ''
      })
    },
    removeApi () {
      this.show = false
      this.$dialog.confirm({
        message: this.$t('pageAuthorize.delete_confirm')
      }).then((res) => {
        this.$toast.loading()
        this.removeApiAccount({ platform: this.currentPlatform ? this.currentPlatform.label : '' }).then((res) => {
          this.$toast(res.msg)
          this.getData()
        }).catch(({ msg }) => this.$toast(msg))
      })
    }
  }
}
</script>

<style scoped lang="less">
.authorize-future-page {
  min-height: 100vh;
  padding: 12px 10px 24px;
  background:
    radial-gradient(circle at top left, rgba(246, 204, 113, 0.18), transparent 24%),
    radial-gradient(circle at 84% 10%, rgba(161, 118, 39, 0.12), transparent 18%),
    linear-gradient(180deg, #050402 0%, #110d07 42%, #181108 100%);
}

.list {
  margin-top: 12px;
  border: 1px solid rgba(217, 176, 92, 0.18);
  border-radius: 16px;
  overflow: hidden;
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.34);
}

.item {
  background-color: transparent;
}
.logo {
  width: 50px;
  display: block;
  &.sinance {
    img {
      width: 40px;
      height: 40px;
      margin: auto;
      display: block;
    }
  }
}
.name {
  font-weight: 700;
  color: #fff1cf;
}
.tip {
  font-size: 12px;
  opacity: 0.8;
  margin-top: 5px;
  color: rgba(240, 227, 197, 0.68);
}
.error {
  color: #ff8078;
}
.sheet-title {
  display: flex;
  flex-direction: column;
  align-items: center;
  font-size: 18px;
  padding: 20px 0;
  color: #fff1cf;
  .logo {
    display: inline-block;
  }
}
.api-popup {
  width: 80vw;
  text-align: center;
  border: 1px solid rgba(217, 176, 92, 0.18);
  border-radius: 16px;
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.98) 0%, rgba(18, 12, 6, 1) 100%);
  .api {
    font-weight: 600;
    margin-bottom: 30px;
    color: #f0e3c5;
  }
}

:deep(.van-cell) {
  background: transparent;
}

:deep(.van-cell::after) {
  left: 16px;
  right: 16px;
  border-color: rgba(217, 176, 92, 0.12);
}
</style>
