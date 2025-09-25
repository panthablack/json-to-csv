import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./resources/js/test/setup.ts']
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './resources/js'),
    },
  },
})