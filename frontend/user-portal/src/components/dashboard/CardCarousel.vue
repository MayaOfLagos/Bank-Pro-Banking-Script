<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import PaymentCard from './PaymentCard.vue'

const props = defineProps({
  cards: { type: Array, default: () => [] },
  currency: { type: String, default: '$' },
  onAddCard: { type: Function, default: null },
})

const emit = defineEmits(['add-card', 'select-card'])

const scroller = ref(null)
const activeIndex = ref(0)

const cardList = computed(() => (Array.isArray(props.cards) ? props.cards : []))
const hasCards = computed(() => cardList.value.length > 0)

// Up to two cards sit side by side inside the box with nothing to scroll, as
// in the reference. The third card is what turns the row into a carousel.
const isSwipeable = computed(() => cardList.value.length > 2)

let scrollListener = null

function updateActiveIndex() {
  const el = scroller.value
  if (!el) return
  // Snap children are equal-width; pick the index closest to scrollLeft.
  const children = Array.from(el.children)
  if (!children.length) return
  const scrollLeft = el.scrollLeft
  // The trailing cards share the last screenful, so their snap points are
  // never reached — at the end of the track the last card is the active one.
  if (scrollLeft >= el.scrollWidth - el.clientWidth - 2) {
    activeIndex.value = children.length - 1
    return
  }
  let closest = 0
  let closestDelta = Infinity
  children.forEach((child, i) => {
    const delta = Math.abs(child.offsetLeft - scrollLeft)
    if (delta < closestDelta) {
      closest = i
      closestDelta = delta
    }
  })
  activeIndex.value = closest
}

onMounted(() => {
  scrollListener = () => updateActiveIndex()
  scroller.value?.addEventListener('scroll', scrollListener, { passive: true })
})
onBeforeUnmount(() => {
  if (scrollListener) scroller.value?.removeEventListener('scroll', scrollListener)
})

watch(cardList, () => {
  activeIndex.value = 0
  scroller.value?.scrollTo({ left: 0, behavior: 'auto' })
})

function handleAdd() {
  if (typeof props.onAddCard === 'function') props.onAddCard()
  emit('add-card')
}

function handleSelect(card) {
  emit('select-card', card)
}
</script>

<template>
  <section class="carousel">
    <div class="head">
      <h2 class="title">Cards</h2>
      <button type="button" class="add" aria-label="Add card" @click="handleAdd">
        <PlusIcon class="add-icon" aria-hidden="true" />
      </button>
    </div>

    <div v-if="!hasCards" class="empty">No cards linked yet.</div>

    <div
      v-else
      ref="scroller"
      class="scroller no-scrollbar"
      :class="{ 'scroller--swipeable': isSwipeable }"
      role="list"
    >
      <button
        v-for="(card, i) in cardList"
        :key="card.id ?? i"
        type="button"
        class="slot"
        role="listitem"
        :aria-label="`Card ${i + 1} of ${cardList.length}`"
        @click="handleSelect(card)"
      >
        <PaymentCard :card="card" :currency="currency" />
      </button>
    </div>

    <div v-if="isSwipeable" class="dots" aria-hidden="true">
      <span
        v-for="(_, i) in cardList"
        :key="i"
        class="dot"
        :class="{ 'dot--active': i === activeIndex }"
      />
    </div>
  </section>
</template>

<style scoped>
.carousel {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-card);
}
.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}
.title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}
.add {
  padding: 0.35rem;
  border: none;
  background: transparent;
  color: var(--text-primary);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: color 0.15s ease, transform 0.08s ease;
}
.add:hover {
  color: var(--accent-strong);
}
.add:active {
  transform: scale(0.92);
}
.add-icon {
  width: 1.25rem;
  height: 1.25rem;
  stroke-width: 2;
}
.empty {
  color: var(--text-secondary);
  font-size: 0.85rem;
  padding: var(--space-6) 0;
  text-align: center;
}
.scroller {
  display: flex;
  gap: var(--space-3);
  overflow: hidden;
  padding-bottom: var(--space-2);
}
.slot {
  /* One or two cards share the box evenly and stay fully visible. */
  flex: 1 1 0;
  min-width: 0;
  background: transparent;
  border: none;
  padding: 0;
  cursor: pointer;
  text-align: left;
}

/* From the third card on, the row scrolls and the next card peeks out to
   advertise that there is more to swipe to. */
.scroller--swipeable {
  overflow-x: auto;
  overflow-y: hidden;
  scroll-snap-type: x mandatory;
  scroll-padding-inline: 0;
}
/* Just under half, so two cards stay the same size they are in the static
   layout and the third peeks in. Every slot matches: the cards take their
   height from their width, so an odd one out would make the row jump. */
.scroller--swipeable .slot {
  flex: 0 0 46%;
  scroll-snap-align: start;
}
.dots {
  display: flex;
  justify-content: center;
  gap: 0.35rem;
  margin-top: var(--space-3);
}
.dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: var(--radius-pill);
  background: var(--divider);
  transition: background-color 0.15s ease, width 0.15s ease;
}
.dot--active {
  background: var(--accent);
  width: 0.9rem;
}
</style>
