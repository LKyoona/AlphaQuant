import * as time from '@/utils/time'
import * as number from '@/utils/number'

export default defineNuxtPlugin((nuxtApp) => {
  const filters = {
    numberFormat: number.format,
    priceFormat: number.priceFormat,
    currency: number.currency,
    timeFormat: time.format
  }

  nuxtApp.vueApp.config.globalProperties.$filters = filters
  nuxtApp.provide('filters', filters)
})
