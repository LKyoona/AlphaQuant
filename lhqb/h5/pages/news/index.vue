<template>
  <div class="news-page">
    <van-nav-bar :title="$t('pageNews.title')" left-arrow @click-left="$router.back()" />
    <div class="news-hero">
      <div>
        <p class="eyebrow">{{ $t('pageNews.eyebrow') }}</p>
        <h1>{{ $t('pageNews.heading') }}</h1>
        <p class="sub">{{ $t('pageNews.sub') }}</p>
      </div>
    </div>
    <div class="news-list">
      <div v-if="loading" class="state-card">{{ $t('pageNews.loading') }}</div>
      <div v-else-if="list.length === 0" class="state-card">{{ $t('pageNews.empty') }}</div>
      <button
        v-for="item in list"
        :key="item.id"
        type="button"
        class="news-card"
        @click="viewDetail(item)"
      >
        <div class="meta">
          <span class="tag">NEWS</span>
          <span class="time">{{ $filters.timeFormat(item.ctime || item.post_date) }}</span>
        </div>
        <div class="title">{{ item.post_title }}</div>
        <div class="desc">{{ item.post_excerpt || item.post_desc || $t('pageNews.read_more') }}</div>
      </button>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex'

export default {
  data () {
    return {
      loading: true,
      list: []
    }
  },
  mounted () {
    this.getNotice({ type: 1 }).then((res) => {
      this.list = res.data.list || []
    }).finally(() => {
      this.loading = false
    })
  },
  methods: {
    ...mapActions({
      getNotice: 'getNotice'
    }),
    viewDetail (item) {
      if (item.visit_url || item.url) {
        this.$router.push(item.visit_url || item.url)
      }
    }
  }
}
</script>

<style scoped lang="less">
.news-page {
  min-height: 100vh;
  background:
    radial-gradient(circle at top left, rgba(246, 204, 113, 0.18), transparent 24%),
    radial-gradient(circle at 84% 10%, rgba(161, 118, 39, 0.12), transparent 18%),
    linear-gradient(180deg, #050402 0%, #110d07 42%, #181108 100%);
  padding-bottom: 20px;
}
.news-hero {
  margin: 12px;
  padding: 18px 16px;
  border-radius: 16px;
  background: linear-gradient(135deg, #18120a, #2a1f0f);
  color: #fff6dc;
}
.eyebrow {
  margin: 0 0 8px;
  color: #d7b365;
  font-size: 12px;
}
h1 {
  margin: 0;
  font-size: 24px;
  line-height: 1.2;
}
.sub {
  margin: 10px 0 0;
  color: rgba(255, 246, 220, 0.74);
  font-size: 12px;
  line-height: 1.6;
}
.news-list {
  padding: 0 12px 20px;
}
.news-card,
.state-card {
  width: 100%;
  margin-bottom: 12px;
  padding: 16px;
  border: 1px solid rgba(217, 176, 92, 0.16);
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.18);
  text-align: left;
  color: #f0e3c5;
}
.news-card {
  display: block;
}
.meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.tag {
  padding: 4px 8px;
  border-radius: 999px;
  background: rgba(240, 196, 110, 0.12);
  color: #f0c46e;
  font-size: 11px;
  font-weight: 700;
}
.time {
  color: rgba(240, 227, 197, 0.58);
  font-size: 11px;
}
.title {
  margin-top: 12px;
  color: #fff1cf;
  font-size: 17px;
  font-weight: 700;
  line-height: 1.4;
}
.desc {
  margin-top: 8px;
  color: rgba(240, 227, 197, 0.68);
  font-size: 13px;
  line-height: 1.6;
}
</style>
