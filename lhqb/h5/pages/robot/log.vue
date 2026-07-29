<template>
  <div>
    <van-nav-bar
      :border="false"
      :title="$t('pageRobot.log')"
      left-arrow
      @click-left="$router.back()"
    />
    <van-pull-refresh
      v-model="refreshing"
      @refresh="onRefresh"
    >
      <van-list
        v-model="loading"
        :finished="finished"
        :finished-text="$t('finished_text')"
        @load="onLoad"
      >
        <van-cell
          v-for="item in logList"
          :key="item.id"
        >
          <div class="robot-item">
            <div class="info">
              <div class="time">{{ item.ctime }}</div>
              <div class="cont">{{ item.content }}</div>
            </div>
          </div>
        </van-cell>
      </van-list>
      <van-empty
        v-if="logList.length === 0"
        :description="$t('empty.log')"
      />
    </van-pull-refresh>
  </div>
</template>

<script>
import { mapActions } from 'vuex'
export default {
  data () {
    return {
      robot_id: this.$route.query.id,
      loading: false,
      finished: false,
      refreshing: false,
      logList: [],
      offset: 0,
      limit: 20
    }
  },
  methods: {
    ...mapActions({
      robotLog: 'robot/robotLog'
    }),
    loadList () {
      if (this.refreshing) {
        this.logList = []
        this.offset = 0
        this.finished = false
        if (this.loading) {
          this.loading = false
          return
        }
      }
      if (this.loading) {
        this.refreshing = false
      }
      const payload = {
        robot_id: this.robot_id,
        limit_begin: this.offset,
        limit_end: this.limit
      }
      this.robotLog(payload)
        .then(({ data }) => {
          const list = data.data
          if (list.length < this.limit) {
            this.finished = true
          } else {
            this.offset += this.limit
          }
          this.logList = this.logList.concat(list)
        })
        .finally(() => {
          this.loading = false
          this.refreshing = false
        })
    },
    onLoad () {
      this.loadList()
    },
    onRefresh () {
      this.loadList()
    }
  }
}
</script>
<style lang="less" scoped>
.van-cell {
  margin: 10px 12px;
  padding: 0;
  border: 1px solid rgba(217, 176, 92, 0.14);
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
  overflow: hidden;
}

.robot-item {
  overflow: hidden;
  .info {
    padding: 12px 14px;
    background: linear-gradient(180deg, rgba(37, 25, 12, 0.92), rgba(22, 14, 7, 0.96));
    font-size: 12px;
    line-height: 1.5;
    color: rgba(240, 227, 197, 0.72);
  }
  .time {
    color: #fff1cf;
    border-bottom: 1px solid rgba(255, 228, 170, 0.12);
    padding-bottom: 8px;
    margin-bottom: 8px;
    font-weight: 700;
  }
}

@media (max-width: 767px) {
  .van-cell {
    margin: 8px 10px;
  }

  .robot-item .info {
    padding: 10px 12px;
  }
}
</style>
