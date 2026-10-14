<script setup>
// Icônes au trait des maquettes ; la couleur suit currentColor
defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 20 },
  stroke: { type: [Number, String], default: 2 },
})

const PATHS = {
  home: ['M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z'],
  friends: [
    { circle: [9, 8, 3.5] },
    'M2.5 20a6.5 6.5 0 0 1 13 0',
    { circle: [17, 9.5, 2.5] },
    'M16 14.2a5 5 0 0 1 5.5 5.8',
  ],
  scan: [
    'M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2',
    { rect: [8.5, 8.5, 7, 7, 1.5] },
  ],
  hanger: ['M8 3q4 3 8 0l5 3-2.5 4.5L16 9.5V21H8V9.5L5.5 10.5 3 6z'],
  user: [{ circle: [12, 8, 4] }, 'M4 21a8 8 0 0 1 16 0'],
  back: ['M15 5l-7 7 7 7'],
  next: ['M9 5l7 7-7 7'],
  down: ['M6 9l6 6 6-6'],
  share: ['M12 15V3M7 8l5-5 5 5M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6'],
  settings: [
    { circle: [12, 12, 3] },
    'M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1',
  ],
  plus: ['M12 5v14M5 12h14'],
  lock: [{ rect: [4, 10, 16, 11, 3] }, 'M8 10V7a4 4 0 0 1 8 0v3'],
  eye: ['M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z', { circle: [12, 12, 3] }],
  box: ['M3 8l9-5 9 5v8l-9 5-9-5z', 'M3 8l9 5 9-5M12 13v8'],
  edit: ['M4 20h4L19 9l-4-4L4 16z', 'M13.5 6.5l4 4'],
  search: [{ circle: [11, 11, 7] }, 'M20 20l-4-4'],
  bell: ['M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z', 'M10 20.5a2 2 0 0 0 4 0'],
  mail: [{ rect: [3, 5, 18, 14, 3] }, 'M4 7l8 6 8-6'],
  arrow: ['M5 12h14M13 6l6 6-6 6'],
  disc: [{ circle: [12, 12, 9] }, { circle: [12, 12, 3] }, 'M12 6.5a5.5 5.5 0 0 1 5.5 5.5'],
  tool: ['M14.7 6.3a4 4 0 0 0-5.4 5.4L3.5 17.5l3 3 5.8-5.8a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z'],
  trash: ['M4 7h16M10 11v6M14 11v6M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13M9 7V4h6v3'],
  close: ['M6 6l12 12M18 6L6 18'],
  logout: ['M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3'],
  tag: ['M3 12V4h8l10 10-8 8z', { circle: [7.5, 7.5, 1.5] }],
  flash: ['M13 2L4 14h7l-1 8 9-12h-7z'],
  image: [{ rect: [3, 4, 18, 16, 3] }, { circle: [9, 10, 2] }, 'M21 16l-5-5-9 9'],
  keyboard: [{ rect: [2, 6, 20, 12, 3] }, 'M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10'],
  check: ['M5 12.5l4.5 4.5L19 7.5'],
}
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    :stroke-width="stroke"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <template v-for="(part, index) in PATHS[name]" :key="index">
      <path v-if="typeof part === 'string'" :d="part" />
      <circle v-else-if="part.circle" :cx="part.circle[0]" :cy="part.circle[1]" :r="part.circle[2]" />
      <rect
        v-else-if="part.rect"
        :x="part.rect[0]"
        :y="part.rect[1]"
        :width="part.rect[2]"
        :height="part.rect[3]"
        :rx="part.rect[4]"
      />
    </template>
  </svg>
</template>
