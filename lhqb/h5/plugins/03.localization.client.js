import { createI18n } from 'vue-i18n'
import enLocale from '@/locales/en'
import zhLocale from '@/locales/zh'
import { langStorage } from '@/utils/storage'

const messages = {
  en: enLocale,
  zh: zhLocale
}

const locales = Object.keys(messages)

export default defineNuxtPlugin((nuxtApp) => {
  const route = useRoute()
  const store = nuxtApp.$store
  let locale = route.query.lang
  const fallbackLocale = 'en'
  const defaultLocale = 'en'

  if (locale && locales.includes(locale)) {
    store.dispatch('setLang', locale)
  } else {
    locale = langStorage.get() || defaultLocale
    if (!locales.includes(locale)) {
      locale = defaultLocale
    }
    store.dispatch('setLang', locale)
  }

  const i18n = createI18n({
    legacy: true,
    globalInjection: true,
    locale: store.state.locale || fallbackLocale,
    fallbackLocale,
    messages,
    silentTranslationWarn: true
  })

  nuxtApp.vueApp.use(i18n)
  nuxtApp.provide('i18n', i18n.global)
})
