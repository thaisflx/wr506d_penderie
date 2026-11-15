<script setup>
import { RouterLink } from 'vue-router'
import { ITEM_STATUS_OPTIONS, optionLabel, statusChip } from '../hierarchy'
import ItemVisual from './ItemVisual.vue'

// Vignette d'objet en grille : carte festonnée comme la page pièce (dressing)
defineProps({
  item: { type: Object, required: true },
  tone: { type: String, default: 'tone-glaucous' },
})
</script>

<template>
  <RouterLink :to="`/items/${item.id}`" class="item-card">
    <span class="item-card-media scallop" :class="item.photo ? '' : tone">
      <span v-if="item.status !== 'available'" class="chip item-card-chip" :class="statusChip(item.status, tone)">
        {{ optionLabel(ITEM_STATUS_OPTIONS, item.status) }}
      </span>
      <ItemVisual :item="item" :category-type="item.categoryType" :size="52" />
    </span>
    <span class="item-card-text">
      <span>
        <span class="item-card-name">{{ item.name }}</span>
        <span class="item-card-meta">{{ item.categoryName }}<template v-if="item.isPersonal"> · Perso</template></span>
      </span>
    </span>
  </RouterLink>
</template>
