<script setup>
import { computed, reactive } from 'vue'

// Formulaire générique de création / modification, construit à partir des champs d'un niveau
const props = defineProps({
  fields: { type: Array, required: true },
  initial: { type: Object, default: null },
  submitLabel: { type: String, default: 'Enregistrer' },
  saving: { type: Boolean, default: false },
  error: { type: Object, default: null },
})
const emit = defineEmits(['submit', 'cancel'])

const values = reactive(
  Object.fromEntries(
    props.fields.map((field) => [
      field.name,
      props.initial?.[field.name] ?? field.default ?? (field.type === 'checkbox' ? false : ''),
    ]),
  ),
)

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
      <label v-if="field.type === 'checkbox'" class="field field-checkbox">
        <input v-model="values[field.name]" type="checkbox" />
        <span>{{ field.label }}</span>
      </label>
      <label v-else class="field">
        <span>{{ field.label }}</span>
        <select v-if="field.type === 'select'" v-model="values[field.name]">
          <option v-for="option in field.options" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <input
          v-else
          v-model.trim="values[field.name]"
          type="text"
          :required="field.required"
          :placeholder="field.placeholder"
        />
        <small v-if="fieldErrors[field.name]" class="error">{{ fieldErrors[field.name] }}</small>
      </label>
    </template>
    <p v-if="generalError" class="error" role="alert">{{ generalError }}</p>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Enregistrement…' : submitLabel }}</button>
      <button type="button" class="btn btn-ghost" @click="emit('cancel')">Annuler</button>
    </div>
  </form>
</template>
