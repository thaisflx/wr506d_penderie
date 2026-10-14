<script setup>
import { RouterLink } from 'vue-router'
import { ITEM_STATUS_OPTIONS, optionLabel } from '../hierarchy'
import ItemVisual from './ItemVisual.vue'

// Vignette d'objet en grille (dressing, profil)
defineProps({
  item: { type: Object, required: true },
  tone: { type: String, default: 'tone-glaucous' },
  compact: { type: Boolean, default: false },
})
</script>

<template>
  <RouterLink v-if="compact" :to="`/items/${item.id}`" class="thumb" :class="tone" :aria-label="item.name">
    <ItemVisual :item="item" :category-type="item.categoryType" />
  </RouterLink>
  <RouterLink v-else :to="`/items/${item.id}`" class="card item-tile">
    <span class="thumb" :class="tone"><ItemVisual :item="item" :category-type="item.categoryType" /></span>
    <span class="item-tile-name">{{ item.name }}</span>
    <span class="item-tile-meta small muted">
      {{ item.categoryName }}<template v-if="item.status !== 'available'"> · {{ optionLabel(ITEM_STATUS_OPTIONS, item.status) }}</template>
    </span>
  </RouterLink>
</template>
