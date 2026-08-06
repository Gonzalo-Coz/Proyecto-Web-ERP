<script setup lang="ts">
import { computed, ref } from 'vue'

/**
 * Selector con búsqueda escrita (typeahead). Reemplaza a un <select> largo:
 * el usuario escribe para filtrar y elige de la lista. Emite el id seleccionado.
 */
const props = withDefaults(
  defineProps<{
    modelValue: number | null
    options: any[]
    optionLabel: (o: any) => string
    valueKey?: string
    placeholder?: string
    disabled?: boolean
  }>(),
  { valueKey: 'id', placeholder: 'Escribe para buscar…', disabled: false },
)

const emit = defineEmits<{
  (e: 'update:modelValue', v: number | null): void
  (e: 'change'): void
}>()

const open = ref(false)
const query = ref('')
const inputEl = ref<HTMLInputElement | null>(null)

const selectedLabel = computed(() => {
  const sel = props.options.find((o) => o[props.valueKey] === props.modelValue)
  return sel ? props.optionLabel(sel) : ''
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  const list = q === '' ? props.options : props.options.filter((o) => props.optionLabel(o).toLowerCase().includes(q))
  return list.slice(0, 50)
})

function onFocus(): void {
  if (props.disabled) return
  open.value = true
  query.value = ''
}

function onBlur(): void {
  // Retraso para que el click en una opción se registre antes de cerrar.
  window.setTimeout(() => {
    open.value = false
    query.value = ''
  }, 150)
}

function select(o: any): void {
  emit('update:modelValue', o[props.valueKey])
  emit('change')
  open.value = false
  query.value = ''
  inputEl.value?.blur()
}
</script>

<template>
  <div class="relative">
    <input
      ref="inputEl"
      :value="open ? query : selectedLabel"
      :placeholder="placeholder"
      :disabled="disabled"
      class="form-input"
      autocomplete="off"
      @focus="onFocus"
      @blur="onBlur"
      @input="query = ($event.target as HTMLInputElement).value; open = true"
    />
    <ul
      v-if="open && filtered.length"
      class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
    >
      <li
        v-for="o in filtered"
        :key="o[valueKey]"
        class="cursor-pointer px-3 py-2 text-sm hover:bg-primary-50"
        @mousedown.prevent="select(o)"
      >
        {{ optionLabel(o) }}
      </li>
    </ul>
    <p
      v-else-if="open && query"
      class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-400 shadow-lg"
    >
      Sin resultados
    </p>
  </div>
</template>
