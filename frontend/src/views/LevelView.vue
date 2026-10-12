<script setup>
import { computed, ref, watch } from 'vue'
import { api } from '../api/client'
import AppBreadcrumbs from '../components/AppBreadcrumbs.vue'
import NodeCard from '../components/NodeCard.vue'
import NodeForm from '../components/NodeForm.vue'
import { LEVELS } from '../hierarchy'

// Affiche les enfants d'un élément de la hiérarchie (ou les logements à la racine)
const props = defineProps({
  // Niveau des éléments listés : place, room, storage, category ou item
  level: { type: String, required: true },
  // Identifiant du parent (absent pour la liste des logements)
  parentId: { type: String, default: null },
})

const config = computed(() => LEVELS[props.level])
const nodes = ref([])
const ancestors = ref([])
const loading = ref(true)
const loadError = ref('')

const creating = ref(false)
const saving = ref(false)
const createError = ref(null)

const title = computed(() => ancestors.value.at(-1)?.name ?? config.value.plural)
const parentLabel = computed(() => (config.value.parentLevel ? LEVELS[config.value.parentLevel].label : null))

// Remonte la hiérarchie depuis le parent pour construire le fil d'Ariane
async function loadAncestors() {
  const chain = []
  let levelKey = config.value.parentLevel
  let iri = levelKey ? `/api${LEVELS[levelKey].resource}/${props.parentId}` : null

  while (levelKey && iri) {
    const level = LEVELS[levelKey]
    const node = await api.get(iri)
    chain.unshift({ name: node.name, link: `${level.resource}/${node.id}`, level: levelKey })
    iri = level.parentField ? node[level.parentField] : null
    levelKey = level.parentLevel
  }
  ancestors.value = chain
}

async function load() {
  loading.value = true
  loadError.value = ''
  creating.value = false
  try {
    const [list] = await Promise.all([api.list(config.value.listPath(props.parentId)), loadAncestors()])
    nodes.value = list
  } catch (e) {
    nodes.value = []
    ancestors.value = []
    loadError.value = e.status === 404 ? 'Cet élément est introuvable.' : e.message
  } finally {
    loading.value = false
  }
}

watch(() => [props.level, props.parentId], load, { immediate: true })

async function create(payload) {
  saving.value = true
  createError.value = null
  try {
    const body = { ...payload }
    // Rattache le nouvel élément à son parent par son IRI
    if (config.value.parentField) {
      body[config.value.parentField] = `/api${LEVELS[config.value.parentLevel].resource}/${props.parentId}`
    }
    const created = await api.post(config.value.resource, body)
    nodes.value.push(created)
    creating.value = false
  } catch (e) {
    createError.value = e
  } finally {
    saving.value = false
  }
}

function replaceNode(updated) {
  nodes.value = nodes.value.map((node) => (node['@id'] === updated['@id'] ? updated : node))
}

function removeNode(deleted) {
  nodes.value = nodes.value.filter((node) => node['@id'] !== deleted['@id'])
}
</script>

<template>
  <section>
    <AppBreadcrumbs v-if="ancestors.length" :ancestors="ancestors" />

    <header class="level-header">
      <div>
        <p v-if="parentLabel" class="eyebrow">{{ parentLabel }}</p>
        <h1>{{ title }}</h1>
        <p v-if="ancestors.length" class="muted">{{ config.plural }}</p>
      </div>
      <button v-if="!creating && !loadError" type="button" class="btn btn-primary" @click="creating = true; createError = null">
        Ajouter {{ config.withArticle }}
      </button>
    </header>

    <div v-if="creating" class="card card-editing">
      <h2 class="card-title">Ajouter {{ config.withArticle }}</h2>
      <NodeForm
        :key="`${level}-${parentId}`"
        :fields="config.fields"
        :saving="saving"
        :error="createError"
        submit-label="Créer"
        @submit="create"
        @cancel="creating = false"
      />
    </div>

    <p v-if="loading" class="muted">Chargement…</p>
    <p v-else-if="loadError" class="error" role="alert">{{ loadError }}</p>
    <p v-else-if="!nodes.length && !creating" class="empty">
      Rien ici pour l'instant. Commence par ajouter {{ config.withArticle }}.
    </p>

    <div v-else class="grid">
      <NodeCard
        v-for="node in nodes"
        :key="node['@id']"
        :level="config"
        :node="node"
        @updated="replaceNode"
        @deleted="removeNode"
      />
    </div>
  </section>
</template>
