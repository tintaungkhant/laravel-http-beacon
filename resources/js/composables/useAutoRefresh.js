import { onActivated, onDeactivated, onMounted, onUnmounted, ref } from 'vue'

const INTERVAL = 5

/**
 * Periodic refresh with a visible countdown.
 *
 * Ticks down 5 → 1, then awaits `refreshFn` before scheduling the next cycle,
 * so a slow refresh never overlaps the next one. The on/off choice is
 * persisted to localStorage under `storageKey` and restored on mount.
 *
 * @param {() => Promise<void>} refreshFn   Loader to run each cycle (should be quiet).
 * @param {string}              storageKey  localStorage key for the on/off flag.
 */
export function useAutoRefresh(refreshFn, storageKey) {
    const autoRefresh = ref(readStored())
    const countdown = ref(INTERVAL)
    const refreshing = ref(false)
    let timer = null

    function readStored() {
        try {
            return localStorage.getItem(storageKey) === '1'
        } catch {
            return false
        }
    }

    function persist() {
        try {
            localStorage.setItem(storageKey, autoRefresh.value ? '1' : '0')
        } catch {
            // ignore (private mode / storage disabled)
        }
    }

    function clearTimer() {
        if (timer) {
            clearTimeout(timer)
            timer = null
        }
    }

    function schedule() {
        timer = setTimeout(tick, 1000)
    }

    async function tick() {
        if (!autoRefresh.value) return

        if (countdown.value > 1) {
            countdown.value--
            schedule()
            return
        }

        refreshing.value = true
        try {
            await refreshFn()
        } finally {
            refreshing.value = false
        }

        if (!autoRefresh.value) return
        countdown.value = INTERVAL
        schedule()
    }

    function run() {
        clearTimer()
        countdown.value = INTERVAL
        schedule()
    }

    function start() {
        autoRefresh.value = true
        persist()
        run()
    }

    function stop() {
        autoRefresh.value = false
        persist()
        clearTimer()
    }

    function toggleAutoRefresh() {
        autoRefresh.value ? stop() : start()
    }

    // Resume a persisted-on choice; clear the timer whenever the view is
    // hidden or gone, without touching the stored flag so the choice survives
    // navigation. onActivated/onDeactivated cover a <KeepAlive>-cached page
    // (so it stops polling while the user is on a detail view); onMounted/
    // onUnmounted cover the non-cached case. run() clears first, so the
    // mount+activate double-fire on first render is harmless.
    function resume() {
        if (autoRefresh.value) run()
    }

    onMounted(resume)
    onActivated(resume)
    onDeactivated(clearTimer)
    onUnmounted(clearTimer)

    return { autoRefresh, countdown, refreshing, toggleAutoRefresh }
}
