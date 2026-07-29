<template>
  <van-popup
    :value="show"
    round
    position="bottom"
    :style="{ height: '75%' }"
    @click-overlay="$emit('close')"
  >
    <van-password-input
      :value="value"
      :focused="showKeyboard"
      :length="6"
      :info="$t('tips.password')"
      style="margin: 40px 0 0"
      @focus="showKeyboard = true"
    />
    <van-number-keyboard
      safe-area-inset-bottom
      :show="showKeyboard"
      @input="(val) => (value += val)"
      @delete="onDelete"
    />
  </van-popup>
</template>

<script>
import { PasswordInput, NumberKeyboard } from 'vant'
export default {
  components: {
    [PasswordInput.name]: PasswordInput,
    [NumberKeyboard.name]: NumberKeyboard
  },
  props: {
    show: {
      type: Boolean,
      default: false
    }
  },
  data () {
    return {
      value: '',
      showKeyboard: true
    }
  },
  watch: {
    value (val) {
      if (val.length === 6) {
        this.$emit('confrim', val)
        this.$nextTick(() => {
          this.value = ''
        })
      }
    }
  },
  methods: {
    onDelete () {
      const value = this.value
      this.value = value.substring(0, value.length - 1)
    }
  }
}
</script>

<style>
</style>
