<script setup>
import BottomSheet from './BottomSheet.vue'
import ItemVisual from './ItemVisual.vue'

// Objet porté dans le look d'une autre personne : lecture seule, jamais l'emplacement (docs/decisions.md, lot 2).
// L'API n'en expose que l'identifiant, le nom, la photo, la catégorie et la marque.
defineProps({
  item: { type: Object, required: true },
})
const emit = defineEmits(['close'])
</script>

<template>
  <BottomSheet :title="item.name" @close="emit('close')">
    <div class="item-preview" data-cy="item-preview">
      <span class="item-preview-visual scallop tone-glaucous">
        <ItemVisual :item="item" :size="72" />
      </span>
      <dl class="card item-infos">
        <div v-if="item.categoryName" class="item-info">
          <dt>Catégorie</dt>
          <dd data-cy="item-preview-category">{{ item.categoryName }}</dd>
        </div>
        <div v-if="item.brand" class="item-info">
          <dt>Marque</dt>
          <dd data-cy="item-preview-brand">{{ item.brand }}</dd>
        </div>
      </dl>
    </div>
  </BottomSheet>
</template>
