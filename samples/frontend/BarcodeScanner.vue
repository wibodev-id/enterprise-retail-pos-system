<template>
  <!--
    Barcode Scanner Component
    
    Hardware barcode scanner integration for Vue.js 3.
    Optimized for fast scanning in POS environments.
    
    @note This is a portfolio sample, not production code.
  -->
  <div class="barcode-scanner">
    <input
      ref="barcodeInputRef"
      v-model="barcodeInput"
      @input="handleInput"
      @keydown.enter.prevent="processBarcode"
      type="text"
      class="barcode-input"
      :placeholder="placeholder"
      :disabled="isProcessing"
      autofocus
    />

    <!-- Visual feedback -->
    <div v-if="isProcessing" class="processing-indicator">
      <span class="spinner"></span>
      Processing...
    </div>

    <!-- Last scanned info -->
    <div v-if="lastScannedProduct" class="last-scanned">
      <span class="product-name">{{ lastScannedProduct.name }}</span>
      <span class="product-price">{{ formatPrice(lastScannedProduct.price) }}</span>
    </div>

    <!-- Error display -->
    <div v-if="error" class="error-message">
      {{ error }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'

// Props
const props = defineProps({
  placeholder: {
    type: String,
    default: 'Scan barcode or type product code...'
  },
  debounceMs: {
    type: Number,
    default: 50 // Optimal for hardware scanners
  },
  minBarcodeLength: {
    type: Number,
    default: 8
  },
  autoFocus: {
    type: Boolean,
    default: true
  }
})

// Emits
const emit = defineEmits(['product-scanned', 'scan-error'])

// Refs
const barcodeInputRef = ref(null)
const barcodeInput = ref('')
const isProcessing = ref(false)
const lastScannedProduct = ref(null)
const error = ref(null)

// Debounce timer
let debounceTimer = null

/**
 * Handle input with debounce.
 * Hardware scanners input characters rapidly - debounce waits for
 * complete barcode before processing.
 */
const handleInput = () => {
  // Clear previous timer
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }

  // Clear error on new input
  error.value = null

  // Set debounce timer
  debounceTimer = setTimeout(() => {
    const barcode = barcodeInput.value.trim()

    // Only process if meets minimum length
    if (barcode && barcode.length >= props.minBarcodeLength) {
      processBarcode()
    }
  }, props.debounceMs)
}

/**
 * Process the scanned barcode.
 * Makes API call to search product by barcode.
 */
const processBarcode = async () => {
  const barcode = barcodeInput.value.trim()

  if (!barcode || barcode.length < props.minBarcodeLength) {
    return
  }

  isProcessing.value = true
  error.value = null

  try {
    // API call to search product
    const response = await fetch('/api/products/search', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({ barcode })
    })

    const data = await response.json()

    if (data.success && data.product) {
      lastScannedProduct.value = data.product
      emit('product-scanned', data.product)

      // Play success sound (optional)
      playBeep('success')
    } else {
      error.value = data.message || 'Product not found'
      emit('scan-error', { barcode, message: error.value })

      // Play error sound
      playBeep('error')
    }
  } catch (err) {
    error.value = 'Failed to search product'
    emit('scan-error', { barcode, message: err.message })
    playBeep('error')
  } finally {
    isProcessing.value = false

    // Clear input and refocus
    barcodeInput.value = ''
    await nextTick()
    focusInput()
  }
}

/**
 * Focus the input field.
 * Called after each scan to allow continuous scanning.
 */
const focusInput = () => {
  if (barcodeInputRef.value) {
    barcodeInputRef.value.focus()
    barcodeInputRef.value.select()
  }
}

/**
 * Play audio feedback.
 * Optional feature for better UX in noisy retail environments.
 */
const playBeep = (type) => {
  const frequencies = {
    success: 800,
    error: 400
  }

  try {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)()
    const oscillator = audioContext.createOscillator()
    const gainNode = audioContext.createGain()

    oscillator.connect(gainNode)
    gainNode.connect(audioContext.destination)

    oscillator.frequency.value = frequencies[type] || 600
    oscillator.type = 'sine'

    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime)
    gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.1)

    oscillator.start(audioContext.currentTime)
    oscillator.stop(audioContext.currentTime + 0.1)
  } catch (e) {
    // Audio not available - silent fail
  }
}

/**
 * Format price for display.
 */
const formatPrice = (price) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(price)
}

/**
 * Handle global keyboard shortcuts.
 * F2 to focus scanner input (common POS shortcut).
 */
const handleKeydown = (event) => {
  if (event.key === 'F2') {
    event.preventDefault()
    focusInput()
  }
}

// Lifecycle
onMounted(() => {
  if (props.autoFocus) {
    focusInput()
  }

  // Register global keyboard listener
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  // Cleanup
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
  window.removeEventListener('keydown', handleKeydown)
})

// Expose methods for parent component
defineExpose({
  focusInput,
  clear: () => {
    barcodeInput.value = ''
    error.value = null
    lastScannedProduct.value = null
  }
})
</script>

<style scoped>
.barcode-scanner {
  position: relative;
  width: 100%;
}

.barcode-input {
  width: 100%;
  padding: 12px 16px;
  font-size: 18px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.barcode-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.barcode-input:disabled {
  background-color: #f1f5f9;
  cursor: not-allowed;
}

.processing-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  color: #64748b;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.last-scanned {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
  padding: 8px 12px;
  background-color: #ecfdf5;
  border-radius: 6px;
}

.product-name {
  font-weight: 500;
  color: #065f46;
}

.product-price {
  font-weight: 600;
  color: #059669;
}

.error-message {
  margin-top: 8px;
  padding: 8px 12px;
  background-color: #fef2f2;
  border-radius: 6px;
  color: #dc2626;
}
</style>
