<template>
  <div class="authorize-page">
    <div class="authorize-shell">
      <van-nav-bar
        fixed
        placeholder
        :title="$t('homeMenu.item1.title')"
        left-arrow
        @click-left="$router.back()"
      />
      <div class="authorize-hero">
        <p class="eyebrow">{{ $t('pageAuthorize.manage') }}</p>
        <h2 class="hero-title">{{ $t('homeMenu.item1.title') }}</h2>
        <p class="hero-sub">{{ $t('pageAuthorize.intro') }}</p>
      </div>

      <div class="list-panel">
        <van-cell
          v-for="(item, index) in platform"
          :key="item.name"
          @click="handlePlatformClick(item, index)"
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
              <div class="desc">
                <span v-if="hasApi(item) && item.status !== -1">{{ $t('pageAuthorize.authorized') }}</span>
                <span v-else-if="item.status === -1" class="error-text">{{ $t('pageAuthorize.expired') }}</span>
                <span v-else>{{ $t('pageAuthorize.not') }}</span>
              </div>
            </van-col>
            <van-col v-if="!item.disabledEdit" class="item-actions">
              <template v-if="hasApi(item)">
                <button class="action-btn" @click.stop="openForm(index)">{{ $t('actions.edit') }}</button>
              </template>
              <button v-else class="action-btn" @click.stop="openForm(index)">{{ $t('actions.create') }}</button>
            </van-col>
          </van-row>
        </van-cell>
      </div>
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
        <span v-if="currentPlatform && !hasApi(currentPlatform)" class="tip">{{ $t('pageAuthorize.not') }}</span>
        <span v-if="currentPlatform && currentPlatform.status === -1" class="tip error">{{ $t('pageAuthorize.expired') }}</span>
      </div>
      <van-cell-group>
        <template v-if="currentPlatform && hasApi(currentPlatform)">
          <van-cell clickable is-link @click.stop="openForm(active)">{{ $t('actions.edit') }}</van-cell>
          <van-cell clickable is-link @click.stop="removeApi">{{ $t('actions.del') }}</van-cell>
        </template>
        <template v-else>
          <van-cell clickable is-link @click.stop="openForm(active)">{{ $t('add') }}</van-cell>
        </template>
      </van-cell-group>
    </van-action-sheet>

  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex'
export default {
  data () {
    return {
      show: false,
      active: 0,
      actions: [{ name: '查看API Key' }, { name: '删除' }, { name: '重置' }]
    }
  },
  computed: {
    ...mapState({
      platform: ({ authorize }) => authorize.platform
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
      getApiAccount: 'authorize/getApiAccount',
      removeApiAccount: 'authorize/removeApiAccount',
      setApiInfo: 'authorize/setApiInfo'
    }),
    getData() {
      (this.platform || []).forEach((item, index) => {
        this.getApiAccount({ platform: item.label }).then((res) => {
          this.setApiInfo([index, this.normalizeApiInfo(res.data)])
        }).catch(() => { })
      })
    },
    normalizeApiInfo (data) {
      const info = data || {}
      const nested = info.account || info.info || {}
      return Object.assign({}, nested, info)
    },
    hasApi (item) {
      return Boolean(
        item &&
        (
          item.api_key ||
          item.secret_key ||
          item.has_api ||
          item.is_bind ||
          item.bind_status === 1 ||
          item.status === 0 ||
          item.status === 1 ||
          item.status === -1
        )
      )
    },
    openSheet (index) {
      this.active = index
      this.show = true
    },
    openForm (index) {
      this.show = false
      this.$router.push('/authorize/form?active=' + index)
    },
    handlePlatformClick (item, index) {
      if (item.disabledEdit) {
        return
      }
      this.active = index
      if (!this.hasApi(item)) {
        this.openForm(index)
        return
      }
      this.show = true
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
.authorize-page {
  position: relative;
  min-height: 100vh;
  padding: 12px 10px 24px;
  background:
    radial-gradient(circle at top left, rgba(246, 204, 113, 0.18), transparent 24%),
    radial-gradient(circle at 84% 10%, rgba(161, 118, 39, 0.12), transparent 18%),
    linear-gradient(180deg, #050402 0%, #110d07 42%, #181108 100%);
  color: #f7ecd2;
  overflow: hidden;
}

.authorize-page::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    linear-gradient(rgba(228, 191, 112, 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(228, 191, 112, 0.03) 1px, transparent 1px);
  background-size: 30px 30px;
  mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.32), transparent 72%);
  pointer-events: none;
  opacity: 0.55;
}

.authorize-shell {
  position: relative;
  z-index: 1;
  max-width: 1200px;
  margin: 0 auto;
}

.authorize-hero,
.list-panel {
  border: 1px solid rgba(217, 176, 92, 0.18);
  border-radius: 16px;
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.34);
}

.authorize-hero {
  padding: 18px 16px 14px;
  margin-top: 12px;
}

.eyebrow {
  margin: 0 0 8px;
  color: #ddb46a;
  font-size: 11px;
  font-weight: 700;
}

.hero-title {
  margin: 0;
  color: #fff1cf;
  font-size: 22px;
  line-height: 1.2;
}

.hero-sub {
  margin: 10px 0 0;
  color: rgba(240, 227, 197, 0.68);
  font-size: 13px;
  line-height: 1.6;
}

.list-panel {
  margin-top: 12px;
  overflow: hidden;
}

.item {
  min-height: 74px;
  background: transparent;
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
  color: #fff1cf;
  font-size: 16px;
  font-weight: 700;
}

.desc {
  margin-top: 6px;
  color: rgba(240, 227, 197, 0.68);
  font-size: 12px;
}

.item-action {
  color: #f0c46e;
}

.item-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.action-btn {
  height: 32px;
  min-width: 56px;
  padding: 0 12px;
  border: 0;
  border-radius: 999px;
  background: linear-gradient(135deg, #8d5c1f, #f1cd86);
  color: #1a1208;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

.action-btn.ghost {
  border: 1px solid rgba(255, 228, 170, 0.18);
  background: rgba(255, 248, 234, 0.06);
  color: #ffe7a8;
}

.tip {
  margin-top: 5px;
  color: rgba(240, 227, 197, 0.6);
  font-size: 12px;
}

.error,
.error-text {
  color: #c84532;
}

.sheet-title {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px 0 18px;
  color: #fff1cf;
  font-size: 18px;

  .logo {
    display: inline-block;
  }
}

.api-popup {
  width: 80vw;
  text-align: center;
  border-radius: 16px;
  border: 1px solid rgba(217, 176, 92, 0.18);
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.98) 0%, rgba(18, 12, 6, 1) 100%);

  .api {
    margin: 0 20px 30px;
    color: #f0e3c5;
    font-weight: 600;
    word-break: break-all;
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

:deep(.van-action-sheet__content) {
  overflow: hidden;
  border-radius: 18px 18px 0 0;
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.98) 0%, rgba(18, 12, 6, 1) 100%);
}
</style>
