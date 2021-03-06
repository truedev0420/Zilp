import Vue from 'vue'
import Vuex from 'vuex'
import Setting from './Setting/index'

import VueGeolocation from 'vue-browser-geolocation';
Vue.use(VueGeolocation);

// Social Login
const HelloJs = require('hellojs/dist/hello.all.min.js');
const VueHello = require('vue-hellojs');

HelloJs.init({
  google: '950347740067-nggt59hraca0ic35606ap6nk0rt2tfh9.apps.googleusercontent.com',
  facebook: "753776052208072"
}, {
  // redirect_uri: 'http://localhost:8080/auth/signin'
  redirect_uri: 'http://78.140.220.40:8080/auth/signin'
});
Vue.use(VueHello, HelloJs);


Vue.use(Vuex)

const debug = process.env.NODE_ENV !== 'production'

export default new Vuex.Store({
  modules: {
    Setting
  },
  state: {
  },
  mutations: {
  },
  actions: {
  },
  getters: {
  },
  strict: debug
})
