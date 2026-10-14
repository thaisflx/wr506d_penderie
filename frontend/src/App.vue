<script setup>
import { RouterView, useRoute } from 'vue-router'
import BottomNav from './components/BottomNav.vue'

const route = useRoute()
</script>

<template>
  <!-- La classe --with-nav réserve la place de la navigation verticale sur ordinateur -->
  <div class="app-shell" :class="{ 'app-shell--with-nav': route.meta.nav }">
    <RouterView :key="route.path" />
    <BottomNav v-if="route.meta.nav" :current="route.meta.nav" />
  </div>
</template>
