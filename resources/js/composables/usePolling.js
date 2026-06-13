import { onBeforeUnmount, ref } from 'vue';

export function usePolling(callback, interval = 4000) {
    const timer = ref(null);

    function start() {
        stop();
        timer.value = window.setInterval(callback, interval);
    }

    function stop() {
        if (timer.value) {
            window.clearInterval(timer.value);
            timer.value = null;
        }
    }

    onBeforeUnmount(stop);

    return { start, stop };
}
