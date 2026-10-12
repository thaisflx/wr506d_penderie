<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api/client'
import { CATEGORY_TYPE_OPTIONS, ITEM_STATUS_OPTIONS, VISIBILITY_OPTIONS, optionLabel } from '../hierarchy'
import NodeForm from './NodeForm.vue'

// Carte d'un élément de la hiérarchie : ouverture, modification et suppression
const props = defineProps({
  level: { type: Object, required: true },
  node: { type: Object, required: true },
})
const emit = defineEmits(['updated', 'deleted'])

const editing = ref(false)
const confirmingDelete = ref(false)
const saving = ref(false)
const error = ref(null)

// Lien vers les enfants : /rooms/3 affiche les rangements de la pièce 3
const childLink = computed(() =>
  props.level.childLevel ? `${props.level.resource}/${props.node.id}` : null,
)

const badges = computed(() => {
  const node = props.node
  const list = []
  if (node.isPersonal) list.push({ text: 'Perso', tone: 'personal' })
  if (node.visibility) list.push({ text: optionLabel(VISIBILITY_OPTIONS, node.visibility), tone: node.visibility })
  if (node.type) list.push({ text: optionLabel(CATEGORY_TYPE_OPTIONS, node.type), tone: 'neutral' })
  if (node.status) list.push({ text: optionLabel(ITEM_STATUS_OPTIONS, node.status), tone: node.status === 'available' ? 'neutral' : 'status' })
  return list
})

const subtitle = computed(() => {
  const parts = []
  if (props.node.city) parts.push(props.node.city)
  if (props.level.countField) parts.push(props.level.countLabel(props.node[props.level.countField] ?? 0))
  return parts.join(' · ')
})

async function save(payload) {
  saving.value = true
  error.value = null
  try {
    const updated = await api.patch(props.node['@id'], payload)
    editing.value = false
    emit('updated', updated)
  } catch (e) {
    error.value = e
  } finally {
    saving.value = false
  }
}

async function remove() {
  error.value = null
  try {
    await api.delete(props.node['@id'])
    emit('deleted', props.node)
  } catch (e) {
    error.value = e
    confirmingDelete.value = false
  }
}
</script>

<template>
  <article class="card" :class="{ 'card-editing': editing }">
    <template v-if="!editing">
      <img v-if="node.photo" :src="node.photo" :alt="node.name" class="card-photo" />
      <component :is="childLink ? RouterLink : 'div'" :to="childLink" class="card-main">
        <h3 class="card-title">{{ node.name }}</h3>
        <p v-if="subtitle" class="muted card-subtitle">{{ subtitle }}</p>
      </component>
      <ul v-if="badges.length" class="badges">
        <li v-for="badge in badges" :key="badge.text" class="badge" :data-tone="badge.tone">{{ badge.text }}</li>
      </ul>
      <div class="card-actions">
        <template v-if="!confirmingDelete">
          <button type="button" class="btn btn-small" @click="editing = true">Modifier</button>
          <button type="button" class="btn btn-small btn-danger-ghost" @click="confirmingDelete = true">Supprimer</button>
        </template>
        <template v-else>
          <span class="confirm-text">
            Supprimer « {{ node.name }} »<template v-if="level.childLevel"> et tout son contenu</template> ?
          </span>
          <button type="button" class="btn btn-small btn-danger" @click="remove">Oui, supprimer</button>
          <button type="button" class="btn btn-small" @click="confirmingDelete = false">Annuler</button>
        </template>
      </div>
      <p v-if="error" class="error" role="alert">{{ error.message }}</p>
    </template>
    <NodeForm
      v-else
      :fields="level.fields"
      :initial="node"
      :saving="saving"
      :error="error"
      submit-label="Enregistrer"
      @submit="save"
      @cancel="editing = false; error = null"
    />
  </article>
</template>
