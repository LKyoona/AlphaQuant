import { createStore } from 'vuex'
import * as rootStore from '~/store'
import * as authorize from '~/store/authorize'
import * as packageStore from '~/store/package'
import * as robot from '~/store/robot'
import * as robotfuture from '~/store/robotfuture'
import * as ticker from '~/store/ticker'
import * as user from '~/store/user'

function normalizeModule(module, namespaced = true) {
  const storeModule = {
    namespaced,
    state: module.state
  }
  if ('getters' in module) storeModule.getters = module.getters
  if ('mutations' in module) storeModule.mutations = module.mutations
  if ('actions' in module) storeModule.actions = module.actions
  return storeModule
}

export default defineNuxtPlugin((nuxtApp) => {
  const store = createStore({
    state: rootStore.state,
    mutations: rootStore.mutations,
    actions: rootStore.actions,
    modules: {
      authorize: normalizeModule(authorize),
      package: normalizeModule(packageStore),
      robot: normalizeModule(robot),
      robotfuture: normalizeModule(robotfuture),
      ticker: normalizeModule(ticker),
      user: normalizeModule(user)
    }
  })

  store.$axios = nuxtApp.$axios
  nuxtApp.vueApp.use(store)
  nuxtApp.provide('store', store)
})
