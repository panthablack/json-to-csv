import { beforeEach, vi } from 'vitest'

// Mock Inertia.js for tests
beforeEach(() => {
  ;(globalThis as any).route = vi.fn()
})