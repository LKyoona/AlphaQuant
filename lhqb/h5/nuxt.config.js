import { DEPLOY_BASE, WEB_URL } from './config/index.js'

const routerBase = DEPLOY_BASE.endsWith('/') ? DEPLOY_BASE : `${DEPLOY_BASE}/`
const asset = (name) => `${routerBase}${name}`.replace(/\/{2,}/g, '/')

export default defineNuxtConfig({
  ssr: false,
  compatibilityDate: '2026-06-23',

  modules: [
    '@nuxtjs/tailwindcss'
  ],

  devServer: {
    port: 8888,
    host: '192.168.109.143'
  },

  app: {
    baseURL: routerBase,
    head: {
      title: 'AI Crypto Star',
      meta: [
        { charset: 'utf-8' },
        {
          name: 'viewport',
          content:
            'width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, viewport-fit=cover'
        },
        { name: 'description', content: '' },
        { name: 'theme-color', content: '#1a1208' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'black-translucent' },
        { name: 'apple-mobile-web-app-title', content: 'AI Crypto Star' }
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: asset('favicon.ico') },
        { rel: 'apple-touch-icon', sizes: '180x180', href: asset('apple-touch-icon.png') },
        { rel: 'manifest', href: asset('site.webmanifest') },
        { rel: 'icon', type: 'image/png', sizes: '192x192', href: asset('icon-192.png') },
        { rel: 'icon', type: 'image/png', sizes: '512x512', href: asset('icon-512.png') }
      ]
    }
  },

  css: ['~/assets/styles/common.less', '~/assets/styles/tailwind.css'],

  runtimeConfig: {
    public: {
      apiBase: WEB_URL
    }
  },

  components: true,

  imports: {
    scan: false
  },

  vite: {
    logLevel: 'error',
    build: {
      chunkSizeWarningLimit: 1000,
      sourcemap: false,
      rollupOptions: {
        onwarn(warning, warn) {
          if (warning.message?.includes('[plugin nuxt:module-preload-polyfill] Sourcemap is likely to be incorrect')) {
            return
          }
          warn(warning)
        },
        output: {
          manualChunks: {
            vue: ['vue', 'vue-router', 'vuex', 'vue-i18n'],
            vant: ['vant'],
            axios: ['axios', 'qs']
          }
        }
      }
    },
    css: {
      preprocessorOptions: {
        less: {
          additionalData: '@import "~/assets/styles/variables.less";',
          modifyVars: {
            hack: 'true; @import "~/assets/styles/variables.less";'
          },
          javascriptEnabled: true
        }
      }
    }
  }
})
