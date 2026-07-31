<template>
  <div class="mail-delivery-notice" role="status" aria-live="polite">
    <div class="notice-mark">!</div>
    <div class="notice-copy">
      <strong>{{ $t('pageSign.code_sent_title') }}</strong>
      <p>{{ $t('pageSign.code_sent_notice') }}</p>
    </div>
  </div>
</template>

<style scoped>
.mail-delivery-notice {
  display: flex;
  gap: 11px;
  align-items: flex-start;
  margin: -2px 0 14px;
  padding: 12px 13px;
  border: 1px solid rgba(232, 185, 87, 0.48);
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(85, 57, 18, 0.36), rgba(16, 13, 9, 0.92));
  box-shadow: inset 0 1px 0 rgba(255, 229, 166, 0.08);
  color: #f6e4b7;
}

.notice-mark {
  flex: 0 0 24px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #e4b454;
  color: #171008;
  font-size: 16px;
  font-weight: 900;
  line-height: 24px;
  text-align: center;
}

.notice-copy {
  min-width: 0;
  font-family: Arial, sans-serif;
}

.notice-copy strong {
  display: block;
  margin-bottom: 3px;
  color: #ffe6a8;
  font-size: 13px;
  line-height: 1.35;
}

.notice-copy p {
  margin: 0;
  color: rgba(255, 239, 205, 0.78);
  font-size: 12px;
  line-height: 1.55;
}
</style>
