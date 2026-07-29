<template>
  <div class="notice-bar" :class="{ empty: !notice.length }">
    <div class="notice-icon">
      <van-icon name="volume-o" />
    </div>
    <div class="notice-viewport">
      <van-swipe
        v-if="notice.length"
        class="notice-swipe"
        vertical
        :autoplay="4000"
        :duration="500"
        :touchable="false"
        :show-indicators="false"
      >
        <van-swipe-item
          v-for="item in notice"
          :key="item.id"
        >
          <button
            type="button"
            class="notice-item"
            @click="viewDetail(item)"
          >
            <span class="notice-tag">{{ $t('homeHero.latest') }}</span>
            <span class="notice-text">{{ item.post_title }}</span>
          </button>
        </van-swipe-item>
      </van-swipe>
      <div v-else class="notice-empty">
        {{ $t('homeHero.latest') }}
      </div>
    </div>
  </div>
</template>

<script>
import { Swipe, SwipeItem } from 'vant'
import { mapActions } from 'vuex'
export default {
  components: {
    [Swipe.name]: Swipe,
    [SwipeItem.name]: SwipeItem
  },
  data () {
    return {
      notice: []
    }
  },
  mounted () {
    this.getNotice({ type: 1 }).then((res) => {
      this.notice = res.data.list || []
    })
  },
  methods: {
    ...mapActions({
      getNotice: 'getNotice'
    }),
    viewDetail (item) {
      this.$router.push('/news')
    }
  }
}
</script>

<style scoped lang="less">
@import './home-theme.less';

.notice-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 54px;
  padding: 0 14px;
  border-radius: 12px;
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.08), transparent 28%),
    linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
  border: 1px solid rgba(217, 176, 92, 0.14);
  overflow: hidden;
  color: @home-text-soft;
}

.notice-bar.empty {
  justify-content: flex-start;
}

.notice-icon {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  color: @home-accent;
  font-size: 18px;
}

.notice-viewport {
  min-width: 0;
  flex: 1;
  height: 28px;
  overflow: hidden;
}

.notice-swipe {
  height: 28px;
}

.notice-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-width: 0;
  height: 28px;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
  text-align: left;
}

.notice-tag {
  flex: 0 0 auto;
  padding: 2px 7px;
  border-radius: 999px;
  background: rgba(240, 196, 110, 0.14);
  color: @home-accent;
  font-size: 11px;
  font-weight: 700;
}

.notice-text {
  color: @home-text;
  font-size: 13px;
  line-height: 1.4;
  white-space: normal;
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.notice-empty {
  color: @home-text-muted;
  font-size: 13px;
}

@media (max-width: 480px) {
  .notice-bar {
    min-height: 50px;
    padding: 0 12px;
    border-radius: 10px;
    gap: 8px;
  }

  .notice-viewport {
    height: 26px;
  }

  .notice-swipe,
  .notice-item {
    height: 26px;
  }

  .notice-icon {
    width: 20px;
    height: 20px;
    font-size: 16px;
  }

  .notice-text {
    font-size: 12px;
  }

  .notice-tag {
    font-size: 10px;
  }
}
</style>
