<template>
  <div>
    <van-nav-bar :title="$t('pageUser.invite')" left-arrow @click-left="$router.back()" />

    <div class="invite-hero">
      <div class="invite-hero-card">
        <div class="hero-wave hero-wave-left"></div>
        <div class="hero-wave hero-wave-right"></div>
        <div class="hero-inner">
          <div class="hero-kicker">{{ $t('pageInvite.invite_kicker') }}</div>
          <div class="hero-title">{{ $t('pageInvite.invite_title') }}</div>
          <div v-if="inviteCode" class="hero-code-wrap">
            <span class="hero-code">{{ inviteCode }}</span>
          </div>
          <div v-if="inviteCode" class="hero-actions">
            <van-button
              v-clipboard:copy="inviteCode"
              v-clipboard:success="onCopy"
              color="linear-gradient(135deg, #f7d48a 0%, #d9a84f 100%)"
              class="hero-btn hero-btn-primary"
            >
              {{ $t('pageInvite.copy_code') }}
            </van-button>
            <van-button
              v-clipboard:copy="inviteUrl"
              v-clipboard:success="onCopyLink"
              plain
              color="#d9b05c"
              class="hero-btn hero-btn-secondary"
            >
              {{ $t('pageInvite.copy_link') }}
            </van-button>
          </div>
          <div class="hero-tip">{{ inviteCode ? $t('pageInvite.invite_tip') : $t('pageInvite.invite_empty_tip') }}</div>
        </div>
      </div>
    </div>

    <div class="invite-code-panel">
      <div class="invite-code-panel-head">
        <div>
          <div class="invite-code-panel-title">{{ $t('pageInvite.code_manage_title') }}</div>
          <div class="invite-code-panel-desc">{{ $t('pageInvite.code_manage_desc') }}</div>
        </div>
        <van-button v-if="!hasPersonalCode" size="small" class="code-action code-create" @click="createCode">
          {{ $t('pageInvite.create_code') }}
        </van-button>
      </div>
      <div v-if="invitationCodes.length" class="invite-code-list">
        <div v-for="item in invitationCodes" :key="item.id || item.code" class="invite-code-item">
          <div class="invite-code-main">
            <span class="invite-code-value">{{ item.code }}</span>
            <span class="invite-code-usage">{{ item.used_count }} / {{ item.max_use_count || $t('pageInvite.unlimited') }}</span>
          </div>
          <div class="invite-code-actions">
            <van-button v-clipboard:copy="item.code" v-clipboard:success="onCopy" size="mini" class="code-action code-copy">{{ $t('pageInvite.copy_code') }}</van-button>
            <van-button v-if="item.id && Number(item.is_self_generated) === 1" size="mini" class="code-action code-toggle" @click="toggleCode(item)">{{ item.status ? $t('pageInvite.disable_code') : $t('pageInvite.enable_code') }}</van-button>
          </div>
        </div>
      </div>
      <div v-else class="invite-code-empty">{{ $t('pageInvite.no_codes') }}</div>
    </div>

    <div v-if="inviteCode" class="invite-qr-panel">
      <div class="invite-qr-box">
        <qrcode-vue
          v-if="inviteUrl"
          :value="inviteUrl"
          :size="132"
          level="H"
        />
      </div>
      <div class="invite-qr-text">
        <div class="invite-qr-title">{{ $t('pageInvite.qr_title') }}</div>
        <div class="invite-qr-desc">{{ $t('pageInvite.qr_desc') }}</div>
        <div class="invite-link-card">
          <div class="invite-link-label">{{ $t('pageInvite.invite_link') }}</div>
          <div class="invite-link-value">{{ inviteUrl }}</div>
        </div>
      </div>
    </div>

    <div class="promotion-pack">
      <div class="promotion-pack-title">{{ $t('pageInvite.promotion_pack_title') }}</div>
      <div class="promotion-pack-list">
        <div class="promotion-pack-item">
          <div class="promotion-pack-item-title">{{ $t('pageInvite.promotion_item_code_title') }}</div>
          <div class="promotion-pack-item-desc">{{ $t('pageInvite.promotion_item_code_desc') }}</div>
        </div>
        <div class="promotion-pack-item">
          <div class="promotion-pack-item-title">{{ $t('pageInvite.promotion_item_qr_title') }}</div>
          <div class="promotion-pack-item-desc">{{ $t('pageInvite.promotion_item_qr_desc') }}</div>
        </div>
        <div class="promotion-pack-item">
          <div class="promotion-pack-item-title">{{ $t('pageInvite.promotion_item_result_title') }}</div>
          <div class="promotion-pack-item-desc">{{ $t('pageInvite.promotion_item_result_desc') }}</div>
        </div>
      </div>
    </div>

    <div class="share-tip">
      <div class="share-tip-title">{{ $t('pageInvite.share_tip_title') }}</div>
      <p>{{ $t('pageInvite.share_tip_1') }}</p>
      <p>{{ $t('pageInvite.share_tip_2') }}</p>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex'
import QrcodeVue from 'qrcode.vue'
export default {
  components: { QrcodeVue },
  data () {
    return {
      invitationCodes: []
    }
  },
  computed: {
    inviteCode () {
      const personalCode = this.invitationCodes.find(item => Number(item.is_self_generated) === 1)
      return (personalCode || this.invitationCodes[0] || {}).code || ''
    },
    hasPersonalCode () {
      return this.invitationCodes.some(item => Number(item.is_self_generated) === 1)
    },
    inviteUrl () {
      return this.inviteCode
        ? window.location.origin + '/app/sign/register?invitation_code=' + encodeURIComponent(this.inviteCode)
        : ''
    }
  },
  mounted () {
    this.loadInvitationCodes()
  },
  methods: {
    ...mapActions({
      invitationCodesAction: 'user/invitationCodes',
      createInvitationCode: 'user/createInvitationCode',
      toggleInvitationCode: 'user/toggleInvitationCode'
    }),
    async loadInvitationCodes () {
      const response = await this.invitationCodesAction()
      this.invitationCodes = response.data && response.data.list ? response.data.list : []
    },
    async createCode () {
      try {
        await this.createInvitationCode({})
        await this.loadInvitationCodes()
        this.$toast(this.$t('create_code_success'))
      } catch (error) {
        this.$toast(error.message || this.$t('create_code_failed'))
      }
    },
    async toggleCode (item) {
      try {
        await this.toggleInvitationCode({ id: item.id })
        await this.loadInvitationCodes()
        this.$toast(this.$t('toggle_code_success'))
      } catch (error) {
        this.$toast(error.message || this.$t('create_code_failed'))
      }
    },
    onCopy () {
      this.$toast(this.$t('actions.copy_success'))
    },
    onCopyLink () {
      this.$toast(this.$t('actions.copy_success'))
    }
  }
}
</script>

<style scoped lang="less">
.invite-hero {
  padding: 16px 15px 0;
}
.invite-hero-card {
  position: relative;
  overflow: hidden;
  border-radius: 22px;
  border: 1px solid rgba(217, 176, 92, 0.2);
  background:
    radial-gradient(circle at top left, rgba(252, 221, 152, 0.18), transparent 28%),
    radial-gradient(circle at bottom right, rgba(217, 176, 92, 0.14), transparent 30%),
    linear-gradient(160deg, #20160d 0%, #130d07 55%, #0d0905 100%);
  box-shadow: 0 18px 48px rgba(0, 0, 0, 0.28);
}
.invite-hero-card::before,
.invite-hero-card::after {
  content: '';
  position: absolute;
  top: 52%;
  width: 18px;
  height: 18px;
  margin-top: -9px;
  border-radius: 50%;
  background: #0f0f14;
  box-shadow: inset 0 0 0 1px rgba(217, 176, 92, 0.1);
}
.invite-hero-card::before {
  left: -9px;
}
.invite-hero-card::after {
  right: -9px;
}
.hero-inner {
  position: relative;
  padding: 28px 22px 24px;
  text-align: center;
}
.hero-kicker {
  color: rgba(255, 241, 207, 0.52);
  font-size: 12px;
  letter-spacing: 3px;
}
.hero-title {
  margin-top: 10px;
  color: #fff2cf;
  font-size: 28px;
  font-weight: 700;
  letter-spacing: 1px;
}
.hero-code-wrap {
  margin: 18px auto 0;
  width: fit-content;
  max-width: 100%;
  padding: 14px 24px;
  border-radius: 18px;
  border: 1px solid rgba(247, 212, 138, 0.2);
  background: linear-gradient(135deg, rgba(255, 248, 234, 0.08), rgba(217, 176, 92, 0.12));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}
.hero-code {
  display: block;
  color: #ffdf84;
  font-size: 30px;
  font-weight: 800;
  letter-spacing: 3px;
  line-height: 1.2;
  word-break: break-all;
}
.hero-actions {
  display: flex;
  gap: 12px;
  margin-top: 18px;
}
.hero-btn {
  flex: 1 1 0;
  height: 42px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 700;
}
.hero-btn-primary :deep(.van-button__text) {
  color: #3f2a11;
}
.hero-btn-secondary {
  background: rgba(255, 255, 255, 0.02);
}
.hero-tip {
  margin-top: 14px;
  color: rgba(240, 227, 197, 0.68);
  font-size: 12px;
  line-height: 1.7;
}
.hero-wave {
  position: absolute;
  top: 26px;
  width: 88px;
  height: 34px;
  opacity: 0.5;
}
.hero-wave::before,
.hero-wave::after {
  content: '';
  position: absolute;
  left: 0;
  width: 100%;
  height: 10px;
  border-top: 3px solid rgba(247, 212, 138, 0.46);
  border-radius: 50%;
}
.hero-wave::before {
  top: 4px;
}
.hero-wave::after {
  top: 18px;
}
.hero-wave-left {
  left: 18px;
}
.hero-wave-right {
  right: 18px;
  transform: scaleX(-1);
}
.invite-qr-panel {
  display: flex;
  align-items: center;
  gap: 16px;
  margin: 15px;
  padding: 16px;
  border-radius: 18px;
  border: 1px solid rgba(217, 176, 92, 0.16);
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.12), transparent 30%),
    linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
  box-shadow: 0 14px 34px rgba(0, 0, 0, 0.24);
}
.invite-code-panel {
  margin: 15px;
  padding: 16px;
  border-radius: 18px;
  border: 1px solid rgba(217, 176, 92, 0.16);
  background: linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
}
.invite-code-panel-head,
.invite-code-main,
.invite-code-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.invite-code-panel-title {
  color: #fff1cf;
  font-size: 17px;
  font-weight: 700;
}
.invite-code-panel-desc,
.invite-code-empty {
  margin-top: 6px;
  color: rgba(240, 227, 197, 0.62);
  font-size: 12px;
}
.invite-code-list {
  margin-top: 14px;
}
.invite-code-item {
  padding: 12px 0;
  border-top: 1px solid rgba(217, 176, 92, 0.12);
}
.invite-code-value {
  color: #ffdf84;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: 1px;
}
.invite-code-usage {
  color: rgba(240, 227, 197, 0.72);
  font-size: 12px;
}
.invite-code-main {
  flex: 1;
  justify-content: flex-start;
  flex-wrap: wrap;
}
.invite-code-actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.code-action {
  min-width: 80px;
  height: 30px;
  padding: 0 11px;
  border: 1px solid rgba(247, 212, 138, 0.28) !important;
  border-radius: 999px;
  background: linear-gradient(145deg, #3a2814, #171008) !important;
  box-shadow: inset 0 1px 0 rgba(255, 244, 214, 0.12), 0 5px 12px rgba(0, 0, 0, 0.18);
  color: #f8d98d !important;
  font-size: 11px;
  font-weight: 700;
}
.code-action :deep(.van-button__text) {
  color: inherit;
}
.code-copy,
.code-create {
  border-color: rgba(248, 215, 144, 0.5) !important;
  background: linear-gradient(135deg, #f5d58c, #bd8131) !important;
  color: #211406 !important;
}
.code-toggle {
  border-color: rgba(239, 193, 105, 0.4) !important;
  background: linear-gradient(145deg, #21160c, #0e0905) !important;
}
.invite-qr-box {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 148px;
  height: 148px;
  border-radius: 18px;
  background: rgba(255, 248, 234, 0.98);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.22);
}
.invite-qr-text {
  flex: 1 1 auto;
}
.invite-qr-title {
  color: #fff1cf;
  font-size: 18px;
  font-weight: 700;
}
.invite-qr-desc {
  margin-top: 8px;
  color: rgba(240, 227, 197, 0.72);
  font-size: 13px;
  line-height: 1.7;
}
.invite-link-card {
  margin-top: 14px;
  padding: 12px 14px;
  border-radius: 14px;
  background: rgba(255, 248, 234, 0.06);
  border: 1px solid rgba(217, 176, 92, 0.12);
}
.invite-link-label {
  color: rgba(240, 227, 197, 0.58);
  font-size: 12px;
}
.invite-link-value {
  margin-top: 6px;
  color: #fff1cf;
  font-size: 12px;
  line-height: 1.6;
  word-break: break-all;
}
.promotion-pack {
  margin: 0 15px 15px;
  padding: 16px;
  border-radius: 18px;
  border: 1px solid rgba(217, 176, 92, 0.16);
  background:
    radial-gradient(circle at top left, rgba(248, 215, 144, 0.08), transparent 26%),
    linear-gradient(180deg, rgba(30, 20, 10, 0.98), rgba(18, 12, 6, 0.98));
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
}
.promotion-pack-title {
  color: #fff1cf;
  font-size: 17px;
  font-weight: 700;
}
.promotion-pack-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-top: 14px;
}
.promotion-pack-item {
  padding: 14px;
  border-radius: 16px;
  background: rgba(255, 248, 234, 0.04);
  border: 1px solid rgba(217, 176, 92, 0.1);
}
.promotion-pack-item-title {
  color: #f0c46e;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.5;
}
.promotion-pack-item-desc {
  margin-top: 8px;
  color: rgba(240, 227, 197, 0.68);
  font-size: 12px;
  line-height: 1.7;
}
.referral-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin: 0 15px 15px;
}
.metric-card {
  padding: 16px;
  border-radius: 18px;
  border: 1px solid rgba(217, 176, 92, 0.14);
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.08), transparent 28%),
    linear-gradient(180deg, rgba(30, 20, 10, 0.98), rgba(18, 12, 6, 0.98));
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
}
.metric-card-highlight {
  grid-column: span 2;
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.12), transparent 28%),
    linear-gradient(135deg, rgba(56, 38, 18, 0.98), rgba(22, 14, 7, 0.98));
}
.metric-card-wide {
  grid-column: span 2;
}
.metric-label {
  color: rgba(240, 227, 197, 0.72);
  font-size: 13px;
}
.metric-value {
  margin-top: 10px;
  color: #ffdf84;
  font-size: 28px;
  font-weight: 800;
  line-height: 1.2;
}
.metric-value-small {
  font-size: 24px;
}
.metric-sub {
  margin-top: 6px;
  color: rgba(240, 227, 197, 0.58);
  font-size: 12px;
}
.user-info {
  border: 1px solid rgba(217, 176, 92, 0.16);
  border-radius: 16px;
  background:
    linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
  margin-bottom: 15px;
  padding: 10px 15px;
  color: #f0e3c5;
  .tag {
    font-size: 16px;
    margin: 0 -15px 10px;
    padding: 0 15px 10px;
    border-bottom: 1px solid rgba(217, 176, 92, 0.12);
  }
  .van-col ~ .van-col {
    position: relative;
    &::before {
      content: '';
      position: absolute;
      top: 15%;bottom: 15%;left: 0;
      width: 1px;background-color: rgba(217, 176, 92, 0.16);
      transform: scaleX(.5);
    }
  }
  .info {
    padding: 10px 0;
    text-align: center;
  }
  .label {
    margin-bottom: 10px;
    font-size: 12px;
  }
  .value {
    font-size: 16px;
    color: #fff1cf;
  }
}
.share-tip {
  margin: 0 15px 15px;
  padding: 14px 15px;
  border-radius: 12px;
  border: 1px solid rgba(217, 176, 92, 0.16);
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.12), transparent 30%),
    linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
  color: rgba(240, 227, 197, 0.72);
  line-height: 1.7;
}
.share-tip-title {
  margin-bottom: 8px;
  color: #f0c46e;
  font-size: 16px;
  font-weight: 700;
}
@media (max-width: 768px) {
  .hero-title {
    font-size: 24px;
  }
  .hero-code {
    font-size: 24px;
    letter-spacing: 2px;
  }
  .invite-qr-panel {
    flex-direction: column;
    text-align: center;
  }
  .promotion-pack-list {
    grid-template-columns: 1fr;
  }
  .hero-actions {
    flex-direction: column;
  }
  .hero-wave {
    width: 64px;
    top: 22px;
  }
  .referral-grid {
    grid-template-columns: 1fr;
  }
  .metric-card-highlight,
  .metric-card-wide {
    grid-column: span 1;
  }
}
</style>
