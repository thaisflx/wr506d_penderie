<script setup>
import { computed, reactive, ref } from 'vue'

// Formulaire générique de création / modification, construit à partir d'une liste de champs.
// Si "deletable" est vrai, propose aussi la suppression avec une confirmation dans le formulaire.
const props = defineProps({
  fields: { type: Array, required: true },
  initial: { type: Object, default: null },
  submitLabel: { type: String, default: 'Enregistrer' },
  saving: { type: Boolean, default: false },
  error: { type: Object, default: null },
  deletable: { type: Boolean, default: false },
  deleteLabel: { type: String, default: 'Supprimer' },
  deleteWarning: { type: String, default: '' },
})
const emit = defineEmits(['submit', 'delete'])

const values = reactive(
  Object.fromEntries(
    props.fields.map((field) => [
      field.name,
      props.initial?.[field.name] ?? field.default ?? (field.type === 'checkbox' ? false : ''),
    ]),
  ),
)
const confirmingDelete = ref(false)

// Messages d'erreur de validation renvoyés par l'API, rangés par champ
const fieldErrors = computed(() =>
  Object.fromEntries((props.error?.violations ?? []).map((v) => [v.propertyPath, v.message])),
)
const generalError = computed(() => {
  if (!props.error) return ''
  const known = props.fields.map((field) => field.name)
  const others = (props.error.violations ?? []).filter((v) => !known.includes(v.propertyPath))
  if (others.length) return others.map((v) => v.message).join(' ')
  return props.error.violations?.length ? '' : props.error.message
})

function submit() {
  const payload = {}
  for (const field of props.fields) {
    const value = values[field.name]
    // Un champ texte facultatif vidé est envoyé à null
    payload[field.name] = field.type === 'text' && !field.required && value === '' ? null : value
  }
  emit('submit', payload)
}
</script>

<template>
  <form class="form" @submit.prevent="submit">
    <template v-for="field in fields" :key="field.name">
      <label v-if="field.type === 'checkbox'" class="toggle">
        <span>{{ field.label }}</span>
        <input v-model="values[field.name]" type="checkbox" :data-cy="`field-${field.name}`" />
      </label>
      <label v-else class="field">
        <span class="field-label">{{ field.label }}</span>
        <select v-if="field.type === 'select'" v-model="values[field.name]" class="input" :data-cy="`field-${field.name}`" :required="field.required">
          <option v-for="option in field.options" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <input
          v-else
          v-model.trim="values[field.name]"
          class="input"
          type="text"
          :data-cy="`field-${field.name}`"
          :required="field.required"
          :placeholder="field.placeholder"
        />
        <small v-if="fieldErrors[field.name]" class="error">{{ fieldErrors[field.name] }}</small>
      </label>
    </template>

    <p v-if="generalError" class="error" role="alert" data-cy="form-error">{{ generalError }}</p>

    <div class="form-actions">
      <button type="submit" class="btn btn--primary btn--large btn--block" data-cy="form-submit" :disabled="saving">
        {{ saving ? 'Enregistrement…' : submitLabel }}
      </button>
      <template v-if="deletable">
        <button v-if="!confirmingDelete" type="button" class="btn btn--danger btn--block" @click="confirmingDelete = true">
          {{ deleteLabel }}
        </button>
        <div v-else class="confirm-box" role="alert">
          <p>{{ deleteWarning || 'Cette suppression est définitive.' }}</p>
          <button type="button" class="btn btn--primary btn--block" @click="emit('delete')">Oui, supprimer</button>
          <button type="button" class="btn btn--soft btn--block" @click="confirmingDelete = false">Annuler</button>
        </div>
      </template>
    </div>
  </form>
</template>
