<template>
  <div>
    <van-nav-bar :border="false" :title="$t('pageUser.history')" left-arrow @click-left="$router.back()" />
    <van-tabs v-model:active="active">
      <van-tab :title="$t('pageCaleandar.log')"><log></log></van-tab>
      <van-tab :title="$t('pageCaleandar.order')"><order></order></van-tab>
    </van-tabs>
  </div>
</template>

<script>
import Log from '@/components/user/calendar/Log'
import Order from '@/components/user/calendar/Order'
export default {
  components: { Log, Order },
  data () {
    return {
      active: 0
    }
  }
}
</script>
<style lang="less" scoped>
div {
  color: #f0e3c5;
}

:deep(.van-nav-bar) {
  background: linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
}

:deep(.van-nav-bar__title),
:deep(.van-nav-bar .van-icon) {
  color: #fff1cf !important;
}

:deep(.van-tabs) {
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.08), transparent 30%),
    linear-gradient(180deg, rgba(18, 12, 6, 0.98), rgba(10, 8, 4, 0.98));
  min-height: calc(100vh - 46px);
}

:deep(.van-tabs__nav) {
  background: transparent !important;
}

:deep(.van-tab) {
  color: rgba(240, 227, 197, 0.58);
  font-weight: 700;
}

:deep(.van-tab--active) {
  color: #fff1cf;
}

:deep(.van-tabs__line) {
  background: linear-gradient(90deg, #8d5c1f, #f1cd86) !important;
}

:deep(.van-tabs__content) {
  padding-bottom: 18px;
}
</style>
