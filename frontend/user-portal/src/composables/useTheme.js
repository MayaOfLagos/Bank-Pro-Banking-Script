import { computed, ref, watch } from 'vue'

const theme = ref(localStorage.getItem('user-portal-theme') || 'light')

const applyTheme = (value) => {
  document.documentElement.classList.toggle('dark', value === 'dark')
}

applyTheme(theme.value)

watch(theme, (value) => {
  localStorage.setItem('user-portal-theme', value)
  applyTheme(value)
})

export const useTheme = () => {
  const isDark = computed(() => theme.value === 'dark')

  const toggleTheme = () => {
    theme.value = isDark.value ? 'light' : 'dark'
  }

  return {
    theme,
    isDark,
    toggleTheme
  }
}
