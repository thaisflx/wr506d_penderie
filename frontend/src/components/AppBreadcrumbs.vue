<script setup>
import { RouterLink } from 'vue-router'

// Fil d'Ariane : Mes logements > Appartement > Chambre > Armoire > T-shirts
defineProps({
  ancestors: { type: Array, required: true },
})
</script>

<template>
  <nav class="breadcrumbs" aria-label="Fil d'Ariane">
    <RouterLink to="/">Mes logements</RouterLink>
    <template v-for="(ancestor, index) in ancestors" :key="ancestor.link">
      <span aria-hidden="true">›</span>
      <span v-if="index === ancestors.length - 1" aria-current="page">{{ ancestor.name }}</span>
      <RouterLink v-else :to="ancestor.link">{{ ancestor.name }}</RouterLink>
    </template>
  </nav>
</template>
